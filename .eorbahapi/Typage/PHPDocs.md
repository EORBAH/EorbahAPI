Oui. Et pour EorbahAPI, c'est même très utile, notamment pour compléter les informations que les types PHP et les Attributes ne peuvent pas exprimer directement.

PHP permet de récupérer les PHPDoc via Reflection.

1. Avec Reflection

Par exemple :

class User
{
    /**
     * Représente le nom public de l'utilisateur.
     *
     * @var string
     */
    public string $name;
}

Tu peux récupérer le DocBlock :

$reflection = new ReflectionClass(User::class);

$property = $reflection->getProperty('name');

$doc = $property->getDocComment();

var_dump($doc);

Tu récupères une chaîne contenant le commentaire :

/**
 * Représente le nom public de l'utilisateur.
 *
 * @var string
 */

Tu peux faire la même chose sur :

ReflectionClass::getDocComment()
ReflectionMethod::getDocComment()
ReflectionFunction::getDocComment()
ReflectionProperty::getDocComment()
ReflectionClassConstant::getDocComment()


---

2. C'est particulièrement intéressant pour les descriptions OpenAPI

Tu pourrais écrire :

class User extends BaseModel
{
    /**
     * Nom de l'utilisateur.
     */
    public string $name;

    /**
     * Adresse email de l'utilisateur.
     */
    public string $email;
}

Ton ModelScanner récupère :

User
 ├── name
 │    └── description = "Nom de l'utilisateur."
 │
 └── email
      └── description = "Adresse email de l'utilisateur."

Puis ton SchemaGenerator produit :

{
  "type": "object",
  "properties": {
    "name": {
      "type": "string",
      "description": "Nom de l'utilisateur."
    },
    "email": {
      "type": "string",
      "description": "Adresse email de l'utilisateur."
    }
  }
}

Donc tu peux utiliser le PHPDoc comme source de documentation.


---

3. Les PHPDoc peuvent aussi contenir des types absents des signatures PHP

C'est là que ça devient vraiment intéressant.

PHP ne permet pas directement :

public array $users;

de dire que c'est un tableau de User.

Mais le PHPDoc peut dire :

/**
 * @var User[]
 */
public array $users;

Ton Reflection voit :

type = array

et ton PHPDoc parser voit :

User[]

Ton TypeResolver peut donc comprendre :

array<User>

et OpenAPI :

{
  "type": "array",
  "items": {
    "$ref": "#/components/schemas/User"
  }
}


---

4. Même chose pour les paramètres

Tu peux avoir :

/**
 * Récupère les utilisateurs actifs.
 *
 * @param int $limit Nombre maximal d'utilisateurs.
 * @return User[]
 */
public function getUsers(int $limit): array
{
}

Reflection te donne :

$limit → int
return → array

Le PHPDoc apporte :

@param
@return User[]

Donc ton Metadata pourrait devenir :

RouteMetadata
│
├── description
│
├── parameters
│    └── limit
│         ├── type = int
│         └── description = "Nombre maximal..."
│
└── response
     └── type = User[]

C'est beaucoup plus riche.


---

5. Attributes + PHPDoc = très puissant

Je te conseille de ne pas choisir entre les deux.

Utilise les Attributes pour les métadonnées structurées :

#[Get('/users/{id}')]
public function getUser(
    #[Path]
    int $id
): User

Et les PHPDoc pour la documentation et certains types complexes :

/**
 * Récupère un utilisateur.
 *
 * Retourne le profil complet de l'utilisateur.
 */
#[Get('/users/{id}')]
public function getUser(
    #[Path]
    int $id
): User

Ton scanner peut produire :

RouteMetadata
│
├── path = /users/{id}
├── method = GET
├── description = "Récupère un utilisateur..."
│
├── parameter
│    └── id
│         ├── type = int
│         └── location = path
│
└── response
     └── type = User


---

6. Mais attention : PHP ne parse pas les PHPDoc

C'est le détail important.

PHP te donne :

$reflection->getDocComment();

mais il ne te transforme pas automatiquement :

@param User[] $users
@return User
@deprecated
@throws Exception

en objets structurés.

Tu récupères simplement une chaîne de caractères.

Donc si EorbahAPI veut exploiter :

@param
@return
@var
@throws
@deprecated

il faut un DocBlock parser.

Tu peux soit en intégrer un existant, soit construire le tien si ton objectif est de maîtriser complètement le système.


---

7. Et pour ton architecture, je ferais ceci

Reflection
   │
   ├── PHP types
   │
   ├── Attributes
   │
   └── PHPDoc
          │
          ▼
    Metadata Builder
          │
          ▼
    Eorbah Metadata
          │
    ┌─────┼─────────┐
    ▼     ▼         ▼
 Router Validator OpenAPI
              │
              ▼
        Schema Generator

Donc PHPDoc ne devrait pas être directement consommé par OpenAPI.

Il devrait être transformé en Metadata, exactement comme les Attributes.


---

8. Exemple complet

Tu pourrais arriver à quelque chose comme :

/**
 * Récupère un utilisateur par son identifiant.
 *
 * @throws UserNotFoundException
 */
#[Get('/users/{id}')]
public function getUser(
    #[Path]
    int $id
): User
{
}

EorbahAPI construit :

RouteMetadata
│
├── method = GET
├── path = /users/{id}
├── description = "Récupère un utilisateur..."
│
├── parameters
│   └── id
│       ├── type = int
│       └── location = path
│
├── response
│   └── type = User
│
└── exceptions
    └── UserNotFoundException

Et ensuite OpenAPI peut générer :

GET /users/{id}
│
├── parameters
│   └── id: integer
│
└── responses
    └── 200
        └── User

avec éventuellement les erreurs documentées.

C'est exactement pourquoi ton idée de Metadata centralisée devient encore plus importante : Attributes, Reflection et PHPDoc deviennent simplement différentes sources alimentant le même modèle interne.