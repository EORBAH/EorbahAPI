Les tags dans FastAPI servent principalement à regrouper et catégoriser les endpoints dans la documentation OpenAPI/Swagger. Ils ne changent pas le comportement de la route.

Par exemple :

from fastapi import APIRouter

router = APIRouter(
    prefix="/users",
    tags=["Users"]
)

@router.get("/")
async def list_users():
    return []

@router.get("/{user_id}")
async def get_user(user_id: int):
    return {"id": user_id}

Dans Swagger, tu auras quelque chose comme :

Users
 ├── GET    /users/
 └── GET    /users/{user_id}

Sans tags, les routes apparaissent généralement sans ce regroupement.

Tu peux aussi mettre plusieurs tags

@router.get(
    "/me",
    tags=["Users", "Authentication"]
)
async def current_user():
    ...

La route apparaîtra alors dans les deux catégories de la documentation.

Et tu peux définir les tags au niveau du router

C'est particulièrement pratique :

users_router = APIRouter(
    prefix="/users",
    tags=["Users"]
)

Toutes les routes du router héritent de ce tag.

Tu peux ensuite avoir :

users_router = APIRouter(
    prefix="/users",
    tags=["Users"]
)

products_router = APIRouter(
    prefix="/products",
    tags=["Products"]
)

orders_router = APIRouter(
    prefix="/orders",
    tags=["Orders"]
)

Swagger devient alors une sorte de :

Users
    GET /users/
    POST /users/
    GET /users/{id}

Products
    GET /products/
    POST /products/

Orders
    GET /orders/
    POST /orders/

Mais il y a un autre niveau

Tu peux également donner des métadonnées aux tags eux-mêmes dans OpenAPI :

app = FastAPI(
    openapi_tags=[
        {
            "name": "Users",
            "description": "Gestion des utilisateurs"
        },
        {
            "name": "Products",
            "description": "Gestion des produits"
        }
    ]
)

FastAPI peut alors utiliser ces descriptions dans Swagger.

Donc les trois concepts sont assez propres :

prefix
   ↓
modifie l'URL

tags
   ↓
organise la documentation

dependencies
   ↓
ajoute des dépendances aux routes

Et surtout, tags n'est pas un mécanisme de sécurité ou de middleware. Mettre :

tags=["Admin"]

ne rend absolument pas la route réservée aux admins. Ça ne fait que dire à Swagger : « classe cette route dans la catégorie Admin ». La sécurité doit être implémentée avec des dépendances, permissions, authentification, etc. Sinon on aurait probablement supprimé 80 % des problèmes de sécurité du Web en ajoutant juste des petits mots entre crochets.