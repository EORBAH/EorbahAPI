#!/usr/bin/env bash
# =============================================================================
# docs_start.sh — synchronise la documentation puis lance le site local.
#
# Équivalent de docs-template/start.sh, avec la génération du site en plus :
# les pages de docs/fr sont converties et copiées dans docs-template, puis un
# serveur statique est lancé (PHP, Python 3 ou npx serve).
#
# Un serveur est nécessaire : docs-template/index.html charge sa configuration,
# ses pages Markdown et ses plugins via fetch(), ce que les navigateurs
# bloquent en ouverture directe (file://).
#
# Usage :
#   ./scripts/docs_start.sh              génère puis sert sur le port 4000
#   ./scripts/docs_start.sh 8080         génère puis sert sur le port 8080
#   ./scripts/docs_start.sh --no-build   sert le site tel quel, sans régénérer
#   ./scripts/docs_start.sh --check      génère puis vérifie (sans écrire)
# =============================================================================

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SITE="$ROOT/docs-template"
PORT="${PORT:-4000}"
BUILD=1

for arg in "$@"; do
  case "$arg" in
    --no-build) BUILD=0 ;;
    --check)    BUILD=2 ;;
    --help|-h)
      sed -n '2,20p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *[!0-9]*)
      echo "Argument inconnu : $arg" >&2
      exit 1
      ;;
    *) PORT="$arg" ;;
  esac
done

PYTHON="$(command -v python3 || command -v python || true)"

if [ "$BUILD" -ne 0 ]; then
  if [ -z "$PYTHON" ]; then
    echo "python3 est requis pour générer le site (scripts/build_docs_site.py)." >&2
    exit 1
  fi

  if [ "$BUILD" -eq 2 ]; then
    "$PYTHON" "$ROOT/scripts/build_docs_site.py" --check
    exit $?
  fi

  echo "→ génération de docs-template depuis docs/fr"
  "$PYTHON" "$ROOT/scripts/build_docs_site.py"
fi

if [ ! -f "$SITE/index.html" ]; then
  echo "Site introuvable : $SITE/index.html" >&2
  exit 1
fi

cd "$SITE"

echo ""
echo "EorbahAPI — documentation"
echo "Dossier : $SITE"
echo "Port    : $PORT"
echo ""

open_browser() {
  URL="http://localhost:$PORT"
  echo "-> $URL"
  ( command -v xdg-open >/dev/null 2>&1 && xdg-open "$URL" ) \
    || ( command -v open >/dev/null 2>&1 && open "$URL" ) \
    || true
}

if command -v php >/dev/null 2>&1; then
  echo "Démarrage avec PHP..."
  ( sleep 1 && open_browser ) &
  exec php -S "localhost:$PORT"

elif command -v python3 >/dev/null 2>&1; then
  echo "Démarrage avec Python 3..."
  ( sleep 1 && open_browser ) &
  exec python3 -m http.server "$PORT"

elif command -v npx >/dev/null 2>&1; then
  echo "Démarrage avec Node (npx serve)..."
  ( sleep 1 && open_browser ) &
  exec npx --yes serve -l "$PORT" .

else
  echo "Aucun serveur trouvé (php, python3 ou npx requis)." >&2
  exit 1
fi
