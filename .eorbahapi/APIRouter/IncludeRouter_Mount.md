Oui, exactement. Et c'est justement l'une des raisons pour lesquelles APIRouter peut donner l'impression de faire la même chose que mount(). Les humains ont encore réussi à créer deux mécanismes qui se ressemblent en surface mais qui n'ont pas le même niveau d'abstraction.

Avec APIRouter, tu peux définir un prefix :

from fastapi import APIRouter

router = APIRouter(prefix="/users")

@router.get("/")
async def list_users():
    return {"users": []}

@router.get("/{user_id}")
async def get_user(user_id: int):
    return {"id": user_id}

Puis :

app.include_router(router)

Les routes deviennent :

GET /users/
GET /users/{user_id}

Tu peux même avoir plusieurs niveaux :

app.include_router(
    router,
    prefix="/api/v1"
)

Si le router possède déjà :

router = APIRouter(prefix="/users")

alors tu obtiens :

/api/v1/users/

Mais prefix ≠ mount

La différence est ce qui se passe derrière le préfixe.

Avec APIRouter :

/api/users
      │
      ▼
route FastAPI
      │
      ▼
fonction Python

Le router fait partie du système de routage de l'application.

Avec mount() :

/api
 │
 ▼
Application ASGI indépendante
 │
 ├── /users
 ├── /products
 └── /whatever

Par exemple :

app.mount("/admin", admin_app)

Si admin_app contient :

@admin_app.get("/users")
async def users():
    ...

la route finale devient :

/admin/users

Mais /admin n'est pas un prefix appliqué aux routes de admin_app au sens FastAPI. C'est une frontière de délégation ASGI.

Le point vraiment important

Avec :

app.include_router(router, prefix="/api")

FastAPI connaît les routes :

/api/users
/api/products

Il peut donc les intégrer à son OpenAPI, à Swagger, aux dépendances FastAPI, etc.

Avec :

app.mount("/api", other_app)

FastAPI sait seulement :

/api/* → other_app

Ce qui se passe ensuite appartient à other_app.

Donc oui :

APIRouter(prefix="/api")

et

app.mount("/api", other_app)

peuvent produire des URLs similaires, mais architecturalement :

prefix = modification du chemin des routes intégrées.

mount = délégation d'une partie de l'arbre HTTP à une autre application.

C'est cette dernière distinction qui devient particulièrement intéressante si tu conçois ton propre framework : APIRouter ressemble à un module de routing, tandis que mount() ressemble à un composition root pour applications ASGI.