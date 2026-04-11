import os
import json
import sqlite3
from flask import Flask, jsonify, request
from flask_cors import CORS
from datetime import datetime

app = Flask(__name__)
CORS(app)

# Database path
DB_PATH = 'truckingdirectory.db'

@app.route('/api/health', methods=['GET'])
def health():
    """Health check endpoint"""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM brokers")
        count = cursor.fetchone()[0]
        conn.close()
        return jsonify({"status": "ok", "brokers": count}), 200
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

@app.route('/api/brokers/search', methods=['GET'])
def search_brokers():
    """Search brokers by state, city, or type"""
    try:
        state = request.args.get('state', '').strip()
        city = request.args.get('city', '').strip()
        broker_type = request.args.get('type', '').strip()
        limit = request.args.get('limit', 50, type=int)
        
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        query = "SELECT * FROM brokers WHERE 1=1"
        params = []
        
        if state:
            query += " AND state = ?"
            params.append(state)
        if city:
            query += " AND city LIKE ?"
            params.append(f"%{city}%")
        if broker_type:
            query += " AND broker_type LIKE ?"
            params.append(f"%{broker_type}%")
        
        query += " LIMIT ?"
        params.append(limit)
        
        cursor.execute(query, params)
        results = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify({"count": len(results), "results": results}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/brokers/<int:broker_id>', methods=['GET'])
def get_broker(broker_id):
    """Get single broker by ID"""
    try:
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        cursor.execute("SELECT * FROM brokers WHERE id = ?", (broker_id,))
        broker = cursor.fetchone()
        conn.close()
        
        if not broker:
            return jsonify({"error": "Broker not found"}), 404
        
        return jsonify(dict(broker)), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/brokers/state/<state>', methods=['GET'])
def brokers_by_state(state):
    """Get brokers by state"""
    try:
        limit = request.args.get('limit', 100, type=int)
        
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        cursor.execute("SELECT * FROM brokers WHERE state = ? LIMIT ?", (state.upper(), limit))
        results = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify({"count": len(results), "state": state, "results": results}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/', methods=['GET'])
def index():
    """Root endpoint"""
    return jsonify({"message": "TruckingDirectory API", "version": "1.0"}), 200

if __name__ == '__main__':
    # Get port from environment or default to 5000
    port = int(os.environ.get('PORT', 5000))
    # Listen on all interfaces (0.0.0.0) so Railway can reach it
    app.run(host='0.0.0.0', port=port, debug=False)
