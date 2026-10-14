Parfait. Je vais vous reformuler l’ensemble des extensions sous forme de packages Composer prêts à être publiés (ou utilisés en local) avec le préfixe eor_bah545/eorbahapi-.
Chaque package correspond à une fonctionnalité modulaire, facile à installer et à activer selon les besoins.

---

📦 Structure générale des packages

· Namespace racine : Eorbahapi\ (pour toutes les classes)
· Package de base (déjà existant) : eor_bah545/eorbahapi-core (contient les interfaces, les classes abstraites BaseSecurity, BaseMiddleware, etc.)
· Packages existants (que vous avez déjà) :
  · eor_bah545/eorbahapi-security-http
  · eor_bah545/eorbahapi-security-apikey
  · eor_bah545/eorbahapi-security-jwt
  · eor_bah545/eorbahapi-security-oauth2
  · eor_bah545/eorbahapi-security-openidconnect
  · eor_bah545/eorbahapi-middleware-ratelimit
  · eor_bah545/eorbahapi-middleware-cors
  · eor_bah545/eorbahapi-middleware-basehttp
  · eor_bah545/eorbahapi-middleware-trustedhost
  · eor_bah545/eorbahapi-rpc-jsonrpc
  · eor_bah545/eorbahapi-mcp (pour le serveur MCP)

---

🆕 Nouveaux packages recommandés

1. Pour le module Security (authentification / autorisation avancée)

Nom du package Description Namespace principal
eor_bah545/eorbahapi-security-rbac Gestion des rôles et permissions (RBAC / ACL) avec vérification granulaire Eorbahapi\Security\Rbac
eor_bah545/eorbahapi-security-csrf Protection CSRF (tokens synchronisés, double-submit cookie) pour applications web Eorbahapi\Security\Csrf
eor_bah545/eorbahapi-security-webauthn Authentification sans mot de passe via WebAuthn / FIDO2 Eorbahapi\Security\Webauthn
eor_bah545/eorbahapi-security-totp 2FA avec TOTP (RFC 6238) – génération et validation de codes Eorbahapi\Security\Totp
eor_bah545/eorbahapi-security-signature Vérification de signature HMAC (style AWS Signature V4) pour API‑to‑API Eorbahapi\Security\Signature
eor_bah545/eorbahapi-security-saml Intégration SSO SAML 2.0 (fournisseurs d’identité entreprise) Eorbahapi\Security\Saml
eor_bah545/eorbahapi-security-magiclink Authentification par lien magique envoyé par email Eorbahapi\Security\MagicLink
eor_bah545/eorbahapi-security-session Gestion sécurisée des sessions PHP (régénération, durée, empreinte) Eorbahapi\Security\Session

---

2. Pour le module Middleware (protection au niveau HTTP)

Nom du package Description Namespace principal
eor_bah545/eorbahapi-middleware-securityheaders Ajoute les en‑têtes de sécurité standard (HSTS, X‑Frame‑Options, etc.) Eorbahapi\Middleware\SecurityHeaders
eor_bah545/eorbahapi-middleware-csp Définit la politique CSP (Content‑Security‑Policy) Eorbahapi\Middleware\Csp
eor_bah545/eorbahapi-middleware-csrf Valide les tokens CSRF dans les requêtes non‑idempotentes Eorbahapi\Middleware\Csrf
eor_bah545/eorbahapi-middleware-ipfilter Filtrage par adresses IP (whitelist / blacklist) Eorbahapi\Middleware\IpFilter
eor_bah545/eorbahapi-middleware-useragent Filtre les User‑Agent suspects ou obsolètes Eorbahapi\Middleware\UserAgent
eor_bah545/eorbahapi-middleware-maxsize Limite la taille des requêtes entrantes (anti‑DoS) Eorbahapi\Middleware\MaxSize
eor_bah545/eorbahapi-middleware-signature Vérifie automatiquement la signature HMAC des requêtes Eorbahapi\Middleware\Signature
eor_bah545/eorbahapi-middleware-audit Journalise chaque requête (IP, utilisateur, méthode, etc.) Eorbahapi\Middleware\Audit
eor_bah545/eorbahapi-middleware-encryption Déchiffre / chiffre le corps des requêtes/réponses Eorbahapi\Middleware\Encryption
eor_bah545/eorbahapi-middleware-circuitbreaker Coupe l’accès à un service en aval en cas d’erreurs répétées Eorbahapi\Middleware\CircuitBreaker
eor_bah545/eorbahapi-middleware-version Gère le versioning d’API avec règles de sécurité associées Eorbahapi\Middleware\Version
eor_bah545/eorbahapi-middleware-validation Valide les schémas JSON ou paramètres avant le contrôleur Eorbahapi\Middleware\Validation

---

3. Sécurisation spécifique pour RPC JSON‑RPC

Nom du package Description Namespace
eor_bah545/eorbahapi-rpc-jsonrpc-security Whitelist de méthodes RPC autorisées, limitation des batches, validation des paramètres Eorbahapi\Rpc\JsonRpc\Security

Ce package peut inclure un middleware dédié et une extension de sécurité qui s’intègre à votre Eorbahapi\RPC\JsonRPC.

---

4. Sécurisation pour MCP (Model Context Protocol)

Nom du package Description Namespace
eor_bah545/eorbahapi-mcp-security Contrôle d’accès aux outils (tools) et ressources (resources) du serveur MCP, protection contre l’injection de prompt Eorbahapi\MCP\Security

Ce package complète votre Eorbahapi\MCP\EorbahapiMCP en ajoutant une couche de sécurité fine.

---

5. Packages transversaux (organisation & résilience)

Nom du package Description Namespace
eor_bah545/eorbahapi-context Détecte automatiquement le type de requête (REST, RPC, MCP) et applique la stratégie de sécurité adaptée Eorbahapi\Context
eor_bah545/eorbahapi-error-handler Centralise la gestion des erreurs de sécurité et uniformise les réponses (codes HTTP, JSON‑RPC, MCP) Eorbahapi\Error
eor_bah545/eorbahapi-config-builder Fournit un builder fluent pour configurer l’ordre d’exécution des middlewares Eorbahapi\Config

---

🧩 Exemple d’installation

```bash
composer require eor_bah545/eorbahapi-security-rbac
composer require eor_bah545/eorbahapi-middleware-securityheaders
composer require eor_bah545/eorbahapi-rpc-jsonrpc-security
```

Chaque package sera autonome, ne dépendra que du package core et pourra être désactivé sans impact sur le reste.

---

✅ Récapitulatif de vos packages existants avec la nouvelle nomenclature

Existant Nouveau nom (si besoin)
BaseSecurity, HTTP, APIKey, JWT, OAuth2, OpenIDConnect déjà sous eor_bah545/eorbahapi-security-*
RateLimiting, CORS, BaseHTTP, TrustedHost déjà sous eor_bah545/eorbahapi-middleware-*
JsonRPC, MCP déjà sous eor_bah545/eorbahapi-rpc-jsonrpc et eor_bah545/eorbahapi-mcp

Vous pouvez donc immédiatement créer ces nouveaux packages et les versionner indépendamment.

Si vous souhaitez que je détaille l’implémentation de l’un d’eux (ex: la structure des classes, les interfaces attendues, ou l’intégration avec votre framework), n’hésitez pas à me le préciser.