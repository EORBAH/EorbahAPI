```bash
curl -X POST http://localhost:8000/rpc \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "method": "addition",
    "params": { "a": 5, "b": 3 },
    "id": 1
  }'
```