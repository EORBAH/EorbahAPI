Oui, mais il faut distinguer Pydantic BaseModel de SQLAlchemy. Ils peuvent travailler ensemble, mais ils ne font pas le même métier.

1. BaseModel définit la forme et valide les données

from pydantic import BaseModel

class User(BaseModel):
    name: str
    age: int
    email: str

Si tu reçois :

{
    "name": "Oumar",
    "age": 19,
    "email": "test@example.com"
}

Pydantic transforme et valide les données.

Par exemple :

User(
    name="Oumar",
    age="19",
    email="test@example.com"
)

Pydantic peut convertir "19" en 19 selon le contexte/configuration.

Mais :

User(
    name="Oumar",
    age="abc",
    email="test@example.com"
)

provoque une erreur de validation.


---

2. Tu peux ajouter des contraintes

Et c'est là que BaseModel devient beaucoup plus intéressant.

Avec Field :

from pydantic import BaseModel, Field

class User(BaseModel):
    name: str = Field(min_length=3, max_length=50)
    age: int = Field(ge=18, le=120)

Tu obtiens :

name
 ├── str
 ├── minimum 3 caractères
 └── maximum 50 caractères

age
 ├── int
 ├── >= 18
 └── <= 120

Tu peux aussi faire :

from pydantic import BaseModel, Field

class Product(BaseModel):
    name: str = Field(min_length=2)
    price: float = Field(gt=0)
    quantity: int = Field(ge=0)


---

3. Tu peux créer tes propres validateurs

Par exemple :

from pydantic import BaseModel, field_validator

class User(BaseModel):
    username: str

    @field_validator("username")
    @classmethod
    def validate_username(cls, value):
        if " " in value:
            raise ValueError("Le username ne doit pas contenir d'espace")

        return value

Donc :

User(username="eorbah")

fonctionne.

Mais :

User(username="eor bah")

échoue.

Tu peux également valider plusieurs champs ou l'ensemble du modèle avec les mécanismes de validation de Pydantic.


---

Et SQLAlchemy dans tout ça ?

SQLAlchemy sert principalement à représenter les données persistées dans la base de données.

Par exemple :

from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column

class Base(DeclarativeBase):
    pass


class User(Base):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str]
    age: Mapped[int]

Ici :

SQLAlchemy
    ↓
Base de données
    ↓
Table users

Alors que :

class UserCreate(BaseModel):
    name: str
    age: int

représente plutôt :

HTTP request
     ↓
JSON
     ↓
Pydantic
     ↓
Validation
     ↓
Python object


---

Et FastAPI utilise énormément cette séparation

Tu peux avoir :

class UserCreate(BaseModel):
    name: str = Field(min_length=3)
    age: int = Field(ge=18)

Puis :

@app.post("/users")
async def create_user(user: UserCreate):
    ...

FastAPI reçoit :

{
    "name": "Oumar",
    "age": 19
}

et fait grosso modo :

HTTP
 ↓
JSON
 ↓
Pydantic
 ↓
UserCreate
 ↓
ta logique métier
 ↓
SQLAlchemy
 ↓
Database

C'est volontairement séparé.


---

Mais Pydantic peut aussi représenter les objets SQLAlchemy

C'est là qu'il y a une fonctionnalité particulièrement utile.

Avec Pydantic v2 :

from pydantic import BaseModel, ConfigDict

class UserResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    name: str
    age: int

Tu peux ensuite créer le modèle Pydantic depuis un objet SQLAlchemy :

user = User(
    id=1,
    name="Oumar",
    age=19
)

response = UserResponse.model_validate(user)

Pydantic lit alors les attributs de l'objet :

SQLAlchemy User
      │
      │ attributes
      ▼
Pydantic UserResponse
      │
      ▼
JSON response

C'est très pratique pour les réponses API.


---

Donc ne pense pas à BaseModel comme à une version légère de SQLAlchemy

Ils répondent à deux problèmes différents :

FastAPI
                    │
       ┌────────────┴────────────┐
       │                         │
    Pydantic                 SQLAlchemy
       │                         │
 validation                persistence
 parsing                   ORM
 sérialisation              database
 API schemas               tables
       │                         │
       └──────────┬──────────────┘
                  │
              application

Et si ta question derrière est « est-ce que je peux construire un système à la Pydantic où un BaseModel accepte des types, des contraintes, des validateurs personnalisés et génère automatiquement un schéma exploitable par FastAPI ? », alors oui, et c'est même essentiellement une des briques fondamentales de l'écosystème FastAPI.