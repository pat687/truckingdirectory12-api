from flask import Flask

app = Flask(__name__)

@app.route('/api/health')
def health():
    return {'status': 'ok'}, 200

@app.route('/')
def index():
    return {'ok': True}, 200

if __name__ == '__main__':
    app.run()
