#!/usr/bin/env bash
# =============================================================================
# start.sh — lance le template de documentation en local.
#
# Un serveur statique est nécessaire (pas d'ouverture directe du fichier
# index.html) car le template charge sa config, ses pages markdown et ses
# plugins via fetch(), ce que les navigateurs bloquent en file://.
#
# Usage :
#   ./start.sh            démarre sur le port 4000
#   ./start.sh 8080        démarre sur le port 8080
# =============================================================================

set -e

PORT="${1:-4000}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

echo "Documentation template"
echo "Dossier : $DIR"
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
  exec php -S localhost:$PORT

elif command -v python >/dev/null 2>&1; then
  echo "Démarrage avec Python..."
  ( sleep 1 && open_browser ) &
  exec python -m SimpleHTTPServer "$PORT"

elif command -v npx >/dev/null 2>&1; then
  echo "Démarrage avec Node (npx serve)..."
  ( sleep 1 && open_browser ) &
  exec npx --yes serve -l "$PORT" .

else
  echo "Aucun serveur trouvé (python3, python ou npx requis)."
  echo "Installe Python 3 ou Node.js, puis relance ./start.sh"
  exit 1
fi
