FastAPI est justement un excellent cas d'école pour ton EorbahAPI, parce que son succès ne vient pas simplement du fait que Sebastián Ramírez aurait crié « mon framework est rapide » sur Internet jusqu'à ce que les humains capitulent.

Il a construit une combinaison assez brillante de produit + démonstration + documentation + distribution communautaire.

1. Le marketing de FastAPI était d'abord le produit

Quand FastAPI apparaît en 2018, Python possède déjà Flask, Django, Falcon, etc.

FastAPI arrive avec un message très clair :

> Modern Python + type hints + validation + OpenAPI + documentation automatique + performance.



Son README actuel conserve cette proposition : rapide, facile à apprendre, rapide à coder, moins de bugs, documentation interactive automatique et standards OpenAPI/JSON Schema. 

Et surtout, il ne fallait presque rien écrire pour obtenir beaucoup de fonctionnalités.

from fastapi import FastAPI

app = FastAPI()

@app.get("/users/{id}")
def get_user(id: int):
    return {"id": id}

Puis immédiatement :

/docs
/redoc
/openapi.json

Tu avais un résultat visuel.

C'est extrêmement important pour le marketing d'un framework.


---

2. La documentation était une arme marketing

C'est probablement l'une des choses que tu devrais le plus copier.

FastAPI ne s'est pas contenté de dire :

> « Voici mon framework. Bonne chance. »



La documentation était construite comme un cours interactif.

Tu apprends progressivement :

First Steps
   ↓
Path Parameters
   ↓
Query Parameters
   ↓
Request Body
   ↓
Validation
   ↓
Security
   ↓
Database
   ↓
Testing
   ↓
Deployment

Et pratiquement chaque concept possède un exemple immédiatement exécutable.

C'est particulièrement efficace pour un framework parce que la documentation devient elle-même un canal d'acquisition.

Quelqu'un cherche :

FastAPI query parameters

Il trouve la documentation.

Il apprend.

Il installe FastAPI.

Il utilise FastAPI.

Il devient potentiellement utilisateur.

Puis il pose une question sur Stack Overflow/GitHub/Reddit.

Et la boucle continue.


---

3. Il a exploité les tendances Python au bon moment

FastAPI n'a pas inventé les type hints.

Mais il a compris quelque chose de très important :

les annotations Python pouvaient devenir une interface de framework.

Par exemple :

def create_user(
    name: str,
    age: int,
    email: EmailStr
):
    ...

Ces annotations pouvaient servir simultanément à :

IDE
 │
 ├── autocomplétion
 │
 ├── type checking
 │
 └── compréhension du code

FastAPI
 │
 ├── validation
 ├── parsing
 ├── OpenAPI
 ├── documentation
 └── erreurs HTTP

Une seule déclaration produit plusieurs fonctionnalités.

C'est une proposition de valeur extrêmement facile à montrer.


---

4. Le benchmark a énormément aidé

FastAPI a aussi utilisé la performance comme argument.

Son README mettait en avant des performances comparables à Node.js et Go, et les benchmarks TechEmpower ont ensuite renforcé cette réputation. 

Mais il y a une nuance importante :

FastAPI repose sur Starlette, qui repose sur Uvicorn.

La hiérarchie est essentiellement :

Uvicorn
   ↓
Starlette
   ↓
FastAPI

Donc annoncer simplement :

> « FastAPI est extrêmement rapide »



sans expliquer la pile serait un peu comme annoncer qu'une voiture est rapide parce que ses pneus sont rapides. Les ingénieurs aiment beaucoup ce genre de simplification marketing.

La documentation FastAPI actuelle explique d'ailleurs explicitement cette nuance. 


---

5. Il a énormément capitalisé sur GitHub

Aujourd'hui, FastAPI possède environ 100 000 étoiles GitHub, plus de 9 000 forks et des centaines de releases. 

Mais surtout, dès les premières années, le dépôt était extrêmement actif.

En 2019, on voit déjà :

issues ;

pull requests ;

contributeurs ;

templates d'issues ;

bot de release ;

documentation ;

exemples ;

générateurs de projets ;

intégrations.


Les release notes montrent par exemple qu'en 2019 FastAPI ajoutait déjà des outils autour de SQLAlchemy, GraphQL, WebSockets, tests, OAuth2, documentation, etc. 

Donc le GitHub lui-même devenait une preuve :

Projet
 ↓
utilisateurs
 ↓
issues
 ↓
contributeurs
 ↓
PR
 ↓
nouvelles fonctionnalités
 ↓
plus d'utilisateurs


---

6. Le projet avait aussi une excellente boucle communautaire

Un truc très intéressant dans les premières issues :

En janvier 2019, quelqu'un écrit en substance :

> « Ce projet semble être sorti de nulle part et être devenu génial en quelques semaines. »



Et Sebastián répond avec l'historique du projet. 

C'est révélateur.

FastAPI n'avait pas besoin d'une campagne publicitaire traditionnelle.

Les développeurs faisaient :

Découverte
    ↓
"Putain c'est pratique"
    ↓
Projet personnel
    ↓
GitHub
    ↓
article / vidéo / tweet
    ↓
autre développeur
    ↓
nouveau projet

Le développeur devient donc le distributeur du framework.

C'est exactement ce que tu veux obtenir avec EorbahAPI.


---

7. Et le nom lui-même est du marketing

Regarde :

FastAPI

Deux mots.

FAST + API

Tu comprends immédiatement :

> API rapide.



Compare ça avec un nom du genre :

EnterprisePHPApplicationFrameworkCore

Même si le second est trois fois meilleur techniquement, personne ne va le chercher volontairement.

Pour EorbahAPI, ton nom a déjà un avantage parce que tu peux créer une identité autour de :

EorbahAPI

et surtout une promesse très précise.

Par exemple :

> FastAPI-like developer experience for modern PHP.



ou, plus agressif :

> The FastAPI experience, for PHP.



Là tu profites directement d'une association mentale existante.


---

8. Et c'est là que ton marketing pourrait être encore plus agressif

Tu as un avantage que FastAPI n'avait pas au départ :

tu sais déjà exactement quel public tu veux attaquer.

PHP possède :

Laravel
Symfony
Slim
Mezzio
Laminas
CodeIgniter
Flight
Spiral
...

Donc tu ne dois surtout pas faire :

> « EorbahAPI est un nouveau framework PHP. »



C'est mort.

Il faut une position.

Par exemple :

Positionnement 1

> FastAPI-inspired PHP framework.



Très simple.

Positionnement 2

> Modern API development in PHP, without the boilerplate.



Plus général.

Positionnement 3

> PHP with the developer experience of FastAPI.



Celui-ci est particulièrement intéressant pour toi.


---

9. Et tu peux reprendre la stratégie FastAPI presque méthodiquement

Je ferais quelque chose comme ça :

EORBAHAPI
                       │
        ┌──────────────┼──────────────┐
        ↓              ↓              ↓
   GitHub          Documentation    Content
        │              │              │
        ↓              ↓              ↓
   Star history    Tutorials       YouTube
   Releases        Examples        Reddit
   Discussions     API reference   X
   Issues          Benchmarks      dev.to
        │              │              │
        └──────────────┼──────────────┘
                       ↓
                  Developers
                       ↓
                  Applications
                       ↓
                 Contributors
                       ↓
                    Growth

Et surtout, chaque fonctionnalité doit devenir du contenu marketing.

Tu ajoutes :

Field attributes

Tu fais :

> How EorbahAPI validates PHP parameters using Attributes



Tu ajoutes OpenAPI :

> Generate OpenAPI documentation automatically in PHP



Tu ajoutes ton système BaseModel :

> Pydantic-like validation in native PHP



Tu optimises le serveur :

> EorbahAPI benchmark: 190k JSON requests/s



Tu ajoutes ton CLI :

> Create a production-ready PHP API in 10 seconds



Et là, tu ne fais plus simplement du marketing.

Tu transformes le développement du framework en machine à contenu.


---

10. Ton benchmark peut devenir particulièrement important

Tu m'avais déjà parlé de ton objectif de :

190k+ req/s JSON
200k+ req/s plain text

Là, attention.

Si tu annonces ça, il faut être extrêmement rigoureux :

Hardware
CPU
RAM
OS
PHP version
PHP SAPI
workers
keep-alive
payload
concurrency
database ou non
middleware
serialization
framework version
benchmark tool

Et publier le code du benchmark.

Parce que :

> EorbahAPI: 200k req/s



est une phrase marketing.

Mais :

> EorbahAPI vs Laravel vs Symfony vs Slim, même machine, même PHP, même payload, benchmark reproductible



est une preuve.

Et la preuve est beaucoup plus puissante.


---

11. Le plus gros enseignement de FastAPI

FastAPI n'a pas vraiment fait :

"MARKETING"

Il a fait :

Produit excellent
                           +
                 proposition simple
                           +
                documentation excellente
                           +
                 GitHub très actif
                           +
                  communauté
                           +
                   benchmarks
                           +
                 contenu technique
                           =
                    distribution

Et ensuite la communauté a fait une partie du marketing à sa place.

Aujourd'hui, le dépôt officiel est à environ 100k+ étoiles, ce qui montre jusqu'où cette boucle peut aller lorsqu'elle fonctionne. 

Pour EorbahAPI, je viserais donc quelque chose de beaucoup plus calculé : ne pas essayer d'être "un autre framework PHP", mais devenir le framework PHP que les développeurs Python/TypeScript reconnaissent immédiatement parce qu'ils retrouvent une expérience FastAPI-like. Là, ton marketing et ton architecture commencent enfin à raconter la même histoire.