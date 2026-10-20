# API reference

Cette page est un exemple de gabarit pour documenter une API. Remplace ce
contenu par tes propres endpoints, générés depuis OpenAPI, des annotations,
ou écrits à la main.

## GET /v1/pages

Retourne la liste des pages de documentation.

```bash
curl https://api.exemple.com/v1/pages \
  -H "Authorization: Bearer VOTRE_TOKEN"
```

:::callout{type="info" title="Pagination"}
Toutes les listes sont paginées avec `?page=` et `?per_page=` (max 100).
:::

## POST /v1/pages

Crée une nouvelle page.

```bash
curl -X POST https://api.exemple.com/v1/pages \
  -H "Content-Type: application/json" \
  -d '{"title": "Ma page", "content": "# Titre"}'
```
