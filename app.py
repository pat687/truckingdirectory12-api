from flask import Flask, jsonify
import os
import sys

app = Flask(__name__)

print("=" * 60)
print("🚀 API Starting")
print(f"CWD: {os.getcwd()}")
print(f"Files: {os.listdir('.')}")
print("=" * 60)

@app.route('/', methods=['GET'])
def root():
    return jsonify({'status': 'ok', 'message': 'API is alive!'}), 200

@app.route('/api/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'ok',
        'message': 'API working',
        'cwd': os.getcwd(),
        'files': os.listdir('.')
    }), 200

@app.errorhandler(Exception)
def handle_error(e):
    print(f"ERROR: {e}")
    return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    print(f"Listening on 0.0.0.0:{port}")
    app.run(host='0.0.0.0', port=port, debug=False)
