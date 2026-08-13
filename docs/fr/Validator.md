# EorbahAPI — Validation avec BaseModel et Field

Le module de validation de EorbahAPI permet de valider automatiquement le corps JSON de la requête à partir d’un modèle déclaratif, inspiré du principe de Pydantic.

## 1. Définir un modèle

```php
use Eorbahapi\Validator\BaseModel;
use Eorbahapi\Validator\Field;

class UserCreate extends BaseModel
{
    public string $name;
    public int $age;
    public ?string $email = null;

    public static function fields(): array
    {
        return [
            'name' => Field::required()->minLength(3),
            'age' => Field::required()->min(18),
            'email' => Field::optional()->email(),
        ];
    }
}
```

Le constructeur de `BaseModel` lit automatiquement le JSON reçu, vérifie les règles déclarées et lève une `ValidationException` si une règle échoue.

---

## 2. Règles disponibles sur `Field`

### Champs requis et optionnels

```php
Field::required()
Field::optional()
```

### Alias de clé JSON

```php
Field::required()->alias('firstName')
```

Dans ce cas, le payload JSON peut utiliser `firstName`, tandis que le modèle expose `first_name`.

### Valeurs numériques

```php
Field::required()->min(18)
Field::required()->max(120)
```

### Longueur de chaîne

```php
Field::required()->minLength(3)
Field::required()->maxLength(100)
```

### Format

```php
Field::required()->email()
Field::required()->regex('/^[a-z0-9_-]+$/i')
Field::required()->oneOf(['admin', 'user'])
```

### Valeur par défaut

```php
Field::optional()->defaultValue('guest')
```

---

## 3. Compatibilité avec le core

Le `BaseModel` est compatible avec le mécanisme d’injection automatique de dépendances. Une route peut accepter une instance du modèle directement :

```php
$app->post('/users', function (UserCreate $user) {
    return [
        'name' => $user->name,
        'age' => $user->age,
    ];
});
```

Si le payload est invalide, le framework renvoie une erreur de validation structurée via `ValidationException`.

---

## 4. Exemple complet

```php
$app->post('/signup', function (UserCreate $user) {
    return [
        'message' => 'utilisateur créé',
        'user' => $user,
    ];
});
```

Payload valide :

```json
{
  "name": "Alice",
  "age": 25,
  "email": "alice@example.com"
}
```

Payload invalide :

```json
{
  "name": "Al",
  "age": 15,
  "email": "not-an-email"
}
```

Cela déclenche une erreur de validation avec les détails du champ concerné.
