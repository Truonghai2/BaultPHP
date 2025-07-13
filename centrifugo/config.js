{
  "admin": true,
  "api_key": "your-secret-api-key",
  "token_hmac_secret_key": "your-jwt-ws-secret-key",
  "allowed_origins": ["http://your-domain.com", "http://localhost:8080"],

  "proxy_connect_endpoint": "grpc://127.0.0.1:10001",
  "proxy_connect_timeout": "3s",

  "proxy_refresh_endpoint": "grpc://127.0.0.1:10001",
  "proxy_refresh_timeout": "3s"
}
