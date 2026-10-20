#!/usr/bin/env bash
# =============================================================================
# docs_start.sh — sert la documentation en local avec MkDocs.
#
# MkDocs reconstruit le site à chaque modification de docs/ : un simple
# rechargement du navigateur suffit pour voir la page mise à jour.
#
# Usage :
#   ./scripts/docs_start.sh              http://localhost:4000
#   ./scripts/docs_start.sh 8080         autre port
#   ./scripts/docs_start.sh --build      construit build/ puis quitte
#   ./scripts/docs_start.sh --check      construit en mode strict (CI)
# =============================================================================

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${PORT:-4000}"
ACTION="serve"

for arg in "$@"; do
  case "$arg" in
    --build) ACTION="build" ;;
    --check) ACTION="check" ;;
    --help|-h)
      sed -n '2,14p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *[!0-9]*)
      echo "Argument inconnu : $arg" >&2
      exit 1
      ;;
    *) PORT="$arg" ;;
  esac
done

PLAIN_PYTHON="$(command -v python3 || command -v python || true)"
if [ -z "$PLAIN_PYTHON" ]; then
  echo "Python 3 est requis pour construire la documentation." >&2
  exit 1
fi

# Interpréteur qui sait faire tourner MkDocs : le venv local, sinon le système.
PYTHON="$PLAIN_PYTHON"
if [ -x "$ROOT/.venv-docs/bin/python" ]; then
  PYTHON="$ROOT/.venv-docs/bin/python"
fi

if ! "$PYTHON" -c "import mkdocs, material, mkdocs_static_i18n" >/dev/null 2>&1; then
  echo "MkDocs n'est pas installé pour $PYTHON."
  echo "→ python scripts/build_docs.py --install"
  echo ""
  echo "Installation dans .venv-docs/ …"
  "$PLAIN_PYTHON" "$ROOT/scripts/build_docs.py" --install >/dev/null
  PYTHON="$ROOT/.venv-docs/bin/python"
fi

case "$ACTION" in
  build)
    exec "$PYTHON" "$ROOT/scripts/build_docs.py"
    ;;
  check)
    exec "$PYTHON" "$ROOT/scripts/build_docs.py" --strict
    ;;
esac

echo "EorbahAPI — documentation"
echo "Source  : $ROOT/docs"
echo "Port    : $PORT"
echo ""

exec "$PYTHON" -m mkdocs serve --dev-addr "localhost:$PORT"
