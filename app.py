import os
import sys
import sqlite3

# Add debugging
print("=" * 60)
print("🚀 Starting TruckingDirectoryUSA API")
print("=" * 60)
print(f"Python: {sys.version}")
print(f"CWD: {os.getcwd()}")
print(f"Files in current dir: {os.listdir('.')}")

# Try to find database
db_paths = [
    'truckingdirectory.db',
    './truckingdirectory.db',
    '/app/truckingdirectory.db',
    os.path.expanduser('~/truckingdirectory.db')
]

DB_PATH = None
for path in db_paths:
    if os.path.exists(path):
        DB_PATH = path
        print(f"✅ Found database at: {DB_PATH}")
        break

if DB_PATH is None:
    print(f"⚠️  Database not found at: {db_paths}")
    print(f"⚠️  Creating empty database at: truckingdirectory.db")
    DB_PATH = 'truckingdirectory.db'

print(f"✅ Using database: {DB_PATH}")
print("=" * 60)

from flask import Flask, request, jsonify
from datetime import datetime

app = Flask(__name__)

def get_db():
    """Connect to database"""
    try:
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        return conn
    except Exception as e:
        print(f"❌ Database Error: {e}")
        return None

@app.route('/api/health', methods=['GET'])
def health():
    """Health check - returns database status"""
    try:
        conn = get_db()
        if conn is None:
            return jsonify({
                'status': 'error',
                'message': 'Database connection failed',
                'db_path': DB_PATH,
                'db_exists': os.path.exists(DB_PATH)
            }), 500
        
        cursor = conn.cursor()
        
        # Check if brokers table exists
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='brokers'")
        brokers_table = cursor.fetchone()
        
        if not brokers_table:
            conn.close()
            return jsonify({
                'status': 'error',
                'message': 'Brokers table not found',
                'db_exists': True,
                'tables_exist': False
            }), 500
        
        # Count brokers
        cursor.execute('SELECT COUNT(*) FROM brokers')
        count = cursor.fetchone()[0]
        
        # Count with emails
        cursor.execute('SELECT COUNT(*) FROM brokers WHERE email IS NOT NULL AND email != ""')
        with_emails = cursor.fetchone()[0]
        
        conn.close()
        
        return jsonify({
            'status': 'ok',
            'brokers': count,
            'brokers_with_emails': with_emails,
            'database': 'connected',
            'db_path': DB_PATH,
            'timestamp': datetime.now().isoformat()
        }), 200
    except Exception as e:
        return jsonify({
            'status': 'error',
            'message': str(e),
            'db_path': DB_PATH,
            'db_exists': os.path.exists(DB_PATH)
        }), 500

@app.route('/api/brokers/search', methods=['GET'])
def search_brokers():
    """Search brokers by state, city, or broker type"""
    try:
        state = request.args.get('state', '').upper()
        city = request.args.get('city', '')
        broker_type = request.args.get('broker_type', '')
        page = int(request.args.get('page', 1))
        limit = int(request.args.get('limit', 20))
        offset = (page - 1) * limit
        
        conn = get_db()
        if conn is None:
            return jsonify({'error': 'Database connection failed'}), 500
        
        cursor = conn.cursor()
        
        # Build query
        query = 'SELECT id, name, phone, website, rating, reviews_count, broker_type, city, state FROM brokers WHERE 1=1'
        params = []
        
        if state:
            query += ' AND state = ?'
            params.append(state)
        if city:
            query += ' AND city LIKE ?'
            params.append(f'%{city}%')
        if broker_type:
            query += ' AND broker_type LIKE ?'
            params.append(f'%{broker_type}%')
        
        # Count total
        count_query = query.replace('SELECT id, name, phone, website, rating, reviews_count, broker_type, city, state', 'SELECT COUNT(*)')
        cursor.execute(count_query, params)
        total = cursor.fetchone()[0]
        
        # Get results
        query += ' LIMIT ? OFFSET ?'
        params.extend([limit, offset])
        
        cursor.execute(query, params)
        brokers = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify({
            'brokers': brokers,
            'total': total,
            'page': page,
            'limit': limit,
            'pages': (total // limit) + (1 if total % limit else 0)
        }), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/brokers/<int:broker_id>', methods=['GET'])
def get_broker(broker_id):
    """Get single broker"""
    try:
        conn = get_db()
        if conn is None:
            return jsonify({'error': 'Database connection failed'}), 500
        
        cursor = conn.cursor()
        cursor.execute('SELECT * FROM brokers WHERE id = ?', (broker_id,))
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            return jsonify({'error': 'Broker not found'}), 404
        
        return jsonify(dict(row)), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/', methods=['GET'])
def root():
    """Root endpoint - shows API info"""
    return jsonify({
        'name': 'TruckingDirectoryUSA API',
        'status': 'running',
        'endpoints': {
            'health': '/api/health',
            'search': '/api/brokers/search?state=TX&limit=5',
            'single': '/api/brokers/<id>'
        }
    }), 200

if __name__ == '__main__':
    # Get port from environment
    port = int(os.environ.get('PORT', 5000))
    host = '0.0.0.0'
    
    print(f"\n✅ API is starting on {host}:{port}")
    print(f"✅ Test: http://localhost:{port}/api/health")
    print("=" * 60 + "\n")
    
    app.run(host=host, port=port, debug=False)
