Oui. Et même mieux: Pydantic n'a absolument pas besoin de FastAPI. FastAPI utilise Pydantic, mais Pydantic est une bibliothèque indépendante de validation, de parsing et de sérialisation de données. FastAPI est simplement l'un de ses utilisateurs les plus visibles. 

Si tu veux vraiment comprendre FastAPI en profondeur, comprendre Pydantic est presque obligatoire, parce que FastAPI délègue une énorme partie de la gestion des données à Pydantic.

1. Pydantic, c'est quoi exactement ?

Le cœur de Pydantic est :

> Décrire la structure de données avec les annotations Python, puis valider, transformer et sérialiser les données selon cette description.



Par exemple :

from pydantic import BaseModel

class User(BaseModel):
    name: str
    age: int
    email: str

Tu viens de définir un schéma de données.

Pydantic sait maintenant que :

User
├── name : str
├── age  : int
└── email: str

Et tu peux faire :

user = User(
    name="Oumar",
    age=19,
    email="test@example.com"
)

user devient un véritable objet Python :

print(user.name)
print(user.age)
print(user.email)


---

2. Mais Pydantic fait beaucoup plus que vérifier les types

Supposons :

user = User(
    name="Oumar",
    age="19",
    email="test@example.com"
)

Pydantic peut effectuer du parsing/coercion selon les règles du type.

Tu peux donc avoir :

user.age

qui devient :

19

Le but n'est donc pas simplement :

"Est-ce un int ?"

mais plutôt :

Données brutes
     ↓
Pydantic
     ↓
Données structurées + validées

C'est une distinction importante.


---

3. BaseModel

BaseModel est probablement la classe que tu utiliseras le plus.

from pydantic import BaseModel

class Product(BaseModel):
    name: str
    price: float
    quantity: int

Tu peux ensuite construire :

product = Product(
    name="Keyboard",
    price=50,
    quantity=2
)

Et obtenir :

product.name
product.price
product.quantity

Mais le modèle est aussi capable de produire des données sérialisables.

product.model_dump()

donne conceptuellement :

{
    "name": "Keyboard",
    "price": 50.0,
    "quantity": 2
}

Et :

product.model_dump_json()

produit du JSON.

Donc BaseModel fait notamment :

BaseModel
                 │
      ┌──────────┼──────────┐
      ↓          ↓          ↓
   parsing    validation  serialization


---

4. Field

Là où ça devient vraiment intéressant, c'est que tes annotations peuvent être accompagnées de contraintes.

from pydantic import BaseModel, Field

class User(BaseModel):
    username: str = Field(
        min_length=3,
        max_length=30
    )

    age: int = Field(
        ge=18,
        le=120
    )

Tu viens de dire :

username
    3 <= longueur <= 30

age
    18 <= age <= 120

Tu peux avoir énormément de contraintes.

Par exemple :

class Product(BaseModel):
    name: str = Field(min_length=2)
    price: float = Field(gt=0)
    stock: int = Field(ge=0)

Ici :

price > 0
stock >= 0


---

5. Types Python avancés

Pydantic exploite énormément le système de typage Python.

Tu peux faire :

from typing import Optional

class User(BaseModel):
    name: str
    age: int
    bio: Optional[str] = None

Ou avec la syntaxe moderne :

class User(BaseModel):
    name: str
    age: int
    bio: str | None = None

Tu peux aussi utiliser :

list[str]
dict[str, int]
tuple[int, str]
set[str]

etc.

Exemple :

class Order(BaseModel):
    products: list[str]
    quantities: dict[str, int]

Pydantic comprend alors la structure récursive.


---

6. Les modèles imbriqués

C'est une des choses les plus puissantes.

class Address(BaseModel):
    city: str
    country: str


class User(BaseModel):
    name: str
    address: Address

Tu peux recevoir :

user = User(
    name="Oumar",
    address={
        "city": "Conakry",
        "country": "Guinea"
    }
)

Pydantic transforme automatiquement la partie address en :

Address(...)

Donc :

user.address.city

fonctionne.

Tu peux ainsi construire des arbres de données complexes :

Order
├── customer
│   ├── name
│   └── address
│       ├── city
│       └── country
│
├── items[]
│   ├── product
│   ├── quantity
│   └── price
│
└── payment
    ├── method
    └── amount


---

7. Les validateurs personnalisés

Tu peux dire :

> "Pydantic, vérifie les types et contraintes standards, mais moi j'ai mes propres règles."



Exemple :

from pydantic import BaseModel, field_validator

class User(BaseModel):
    username: str

    @field_validator("username")
    @classmethod
    def validate_username(cls, value):
        if " " in value:
            raise ValueError(
                "Username cannot contain spaces"
            )

        return value

Maintenant :

User(username="eorbah")

fonctionne.

Mais :

User(username="eor bah")

échoue.


---

8. Validation de plusieurs champs

Tu peux également effectuer des validations qui dépendent de plusieurs valeurs.

Par exemple :

from pydantic import BaseModel, model_validator

class PasswordChange(BaseModel):
    password: str
    password_confirmation: str

    @model_validator(mode="after")
    def passwords_match(self):
        if self.password != self.password_confirmation:
            raise ValueError("Passwords do not match")

        return self

Ici la validation concerne le modèle entier, pas simplement un champ.

C'est très important dans les API.


---

9. Pydantic connaît beaucoup de types

Pydantic possède des types et contraintes pratiques.

Par exemple :

from pydantic import BaseModel, EmailStr, HttpUrl

class User(BaseModel):
    email: EmailStr
    website: HttpUrl

Tu peux donc avoir :

EmailStr
HttpUrl
UUID
datetime
date
IPv4Address
IPv6Address
Decimal
...

Et tu peux également utiliser des types Python standards.


---

10. Annotated

Pydantic s'intègre également très bien avec Annotated.

Par exemple :

from typing import Annotated
from pydantic import BaseModel, Field

class User(BaseModel):
    age: Annotated[int, Field(ge=18)]

Cela permet de séparer :

type
+
metadata / contraintes

C'est particulièrement intéressant dans des architectures où plusieurs outils exploitent les annotations.


---

11. Alias

Tu peux faire correspondre un nom Python à un nom externe différent.

from pydantic import BaseModel, Field

class User(BaseModel):
    first_name: str = Field(alias="firstName")

Tu peux donc recevoir :

{
    "firstName": "Oumar"
}

tout en ayant :

user.first_name

dans Python.

Très utile quand ton API utilise du camelCase mais ton code Python du snake_case.


---

12. Serialization

Pydantic ne fait pas seulement :

JSON → Python

Il peut aussi faire :

Python → données sérialisables

Par exemple :

user.model_dump()

ou :

user.model_dump_json()

Tu peux également contrôler la sérialisation.

Par exemple :

user.model_dump(
    exclude_none=True
)

Cela permet de supprimer certains champs de la sortie.


---

13. JSON Schema

Et là, on arrive à la raison pour laquelle Pydantic est tellement intéressant pour FastAPI.

Pydantic peut générer un JSON Schema à partir de ton modèle.

Par exemple :

class User(BaseModel):
    name: str
    age: int

Pydantic peut produire un schéma décrivant :

User
├── name → string
└── age  → integer

Ce schéma peut ensuite être utilisé par d'autres systèmes.

C'est justement une des briques qui permettent à FastAPI de construire automatiquement OpenAPI/Swagger.


---

14. Pydantic n'est donc pas un ORM

C'est une distinction essentielle.

Pydantic :

Validation
Parsing
Serialization
Schema

SQLAlchemy :

ORM
SQL
Database
Relations
Transactions
Persistence

Tu peux avoir :

HTTP
 │
 ▼
Pydantic
 │
 ▼
Business logic
 │
 ▼
SQLAlchemy
 │
 ▼
PostgreSQL

Pydantic ne remplace pas SQLAlchemy.


---

15. Et Pydantic fonctionne parfaitement sans FastAPI

Oui.

Tu peux simplement installer Pydantic :

pip install pydantic

Puis :

from pydantic import BaseModel

class User(BaseModel):
    name: str
    age: int

user = User(
    name="Oumar",
    age=19
)

print(user)

Aucun serveur.

Aucun HTTP.

Aucun FastAPI.

Aucun Uvicorn.

Rien.

Juste Pydantic.


---

16. Et c'est utilisé pour beaucoup d'autres choses

Par exemple une configuration :

from pydantic import BaseModel

class Config(BaseModel):
    host: str
    port: int
    debug: bool

config = Config(
    host="localhost",
    port=8000,
    debug=True
)

Ou des données provenant d'un fichier :

JSON
YAML
TOML
environment variables
API externe
database
CLI

Tu peux les faire passer par Pydantic pour obtenir une structure Python fiable.


---

17. Pydantic Settings

Il existe également pydantic-settings, qui étend cette philosophie à la configuration d'applications.

Conceptuellement :

.env
 │
 ▼
environment variables
 │
 ▼
Pydantic Settings
 │
 ▼
configuration typée

Par exemple :

from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    database_url: str
    debug: bool = False

Puis tes variables d'environnement deviennent un objet typé.

Très pratique pour les applications.


---

18. TypeAdapter

Une partie que les débutants découvrent moins vite mais qui est extrêmement intéressante : Pydantic n'a pas besoin d'un BaseModel pour valider des données.

Tu peux utiliser TypeAdapter.

Par exemple :

from pydantic import TypeAdapter

adapter = TypeAdapter(list[int])

result = adapter.validate_python(
    ["1", "2", "3"]
)

Tu peux alors travailler directement avec un type :

list[int]
dict[str, float]
list[User]
Union[...]

sans créer obligatoirement un modèle.

C'est une distinction importante :

BaseModel
    ↓
modèle de données structuré

TypeAdapter
    ↓
validation d'un type quelconque


---

19. Dataclasses

Pydantic peut également fonctionner avec des dataclasses.

Il existe notamment :

from pydantic.dataclasses import dataclass

@dataclass
class User:
    name: str
    age: int

Tu gardes donc une API proche des dataclasses Python tout en bénéficiant de la validation Pydantic.


---

20. ORM / objets existants

Et comme on en parlait avec SQLAlchemy, Pydantic peut également valider des données provenant d'objets possédant des attributs, avec la configuration appropriée :

from pydantic import BaseModel, ConfigDict

class UserResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    name: str

Cela permet notamment de faire :

SQLAlchemy object
       ↓
Pydantic model
       ↓
API response


---

21. Pydantic possède donc plusieurs rôles

Si on prend un peu de recul :

PYDANTIC
                            │
       ┌────────────────────┼────────────────────┐
       │                    │                    │
       ▼                    ▼                    ▼
   Validation           Parsing             Serialization
       │                    │                    │
       ▼                    ▼                    ▼
  contraintes          données brutes       JSON/dict
       │
       ▼
     Schema
       │
       ▼
   JSON Schema

Et autour de ça tu as :

BaseModel
Field
Validators
TypeAdapter
Dataclasses
Settings
JSON Schema
Serialization
Aliases
Custom types
Generics
Unions
Nested models


---

22. Le rapport avec FastAPI devient maintenant évident

FastAPI fait essentiellement :

FastAPI
                     │
        ┌────────────┼────────────┐
        │            │            │
        ▼            ▼            ▼
     Routing      Pydantic      OpenAPI
        │            │
        │            ▼
        │        validation
        │            │
        └────────────┤
                     ▼
                  endpoint

Quand tu écris :

@app.post("/users")
async def create_user(user: User):
    ...

FastAPI exploite ton User Pydantic pour savoir :

comment parser la requête

comment valider les données

quels champs sont obligatoires

quels types sont attendus

quelles erreurs retourner

quel schéma mettre dans OpenAPI

comment documenter ton endpoint


Donc Pydantic n'est pas une fonctionnalité de FastAPI.

C'est plutôt :

Pydantic = moteur de données / validation

FastAPI = framework HTTP qui l'exploite

Et cette séparation est précisément ce qui rend Pydantic réutilisable ailleurs. Même des projets de l'écosystème Pydantic, comme FastUI, utilisent Pydantic indépendamment de FastAPI. 

Le point qui devrait particulièrement t'intéresser

Si tu réfléchis à construire ton propre framework PHP, Pydantic est probablement une des briques de FastAPI les plus intéressantes à étudier indépendamment du HTTP.

Tu pourrais imaginer quelque chose comme :

EorbahSchema
      │
      ├── types
      ├── validation
      ├── coercion
      ├── nested schemas
      ├── serialization
      ├── aliases
      ├── custom validators
      └── JSON Schema
              │
       ┌──────┴─────────┐
       ▼                ▼
   EorbahAPI        EorbahCLI
       │
       ▼
      HTTP

Autrement dit, tu pourrais avoir ton équivalent de Pydantic sans qu'il connaisse même l'existence de FastAPI, puis faire utiliser cette bibliothèque par ton framework HTTP. C'est justement une architecture beaucoup plus propre que de mélanger validation, routing et transport HTTP dans une seule classe monolithique.