# API reference

Example template for documenting an API — replace with your own endpoints.

## GET /v1/pages

Returns the list of documentation pages.

```bash
curl https://api.example.com/v1/pages \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## POST /v1/pages

Creates a new page.

```bash
curl -X POST https://api.example.com/v1/pages \
  -H "Content-Type: application/json" \
  -d '{"title": "My page", "content": "# Title"}'
```
