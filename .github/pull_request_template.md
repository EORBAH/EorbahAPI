## Résumé

<!-- Décrivez brièvement ce que change cette PR et pourquoi. Concentrez-vous sur l'impact et la raison. -->

## Détails

<!-- Ajoutez le contexte ou les décisions de conception supplémentaires. Soyez bref mais complet. -->

## Problèmes associés

<!-- Utilisez les mots-clés pour fermer automatiquement les issues (Closes #123, Fixes #456). Si c'est seulement lié ou partiel, mentionnez simplement le numéro d'issue (Related to #123). -->

## Validation

<!-- Listez les étapes exactes pour que le relecteur puisse valider les changements. Incluez les commandes, les résultats attendus et les cas limites. -->

## Checklist avant fusion

<!-- Cochez tout ce qui s'applique avant de demander une revue ou de fusionner. -->

- [ ] Documentation ou README mis à jour si nécessaire
- [ ] Tests ajoutés ou mis à jour si nécessaire
- [ ] Changements incompatibles (breaking changes) identifiés si besoin
- [ ] Validation effectuée sur les plateformes requises :
  - [ ] MacOS
    - [ ] composer install
    - [ ] ./vendor/bin/phpunit
    - [ ] php -l src/ && php -l tests/
    - [ ] Docker (si applicable)
  - [ ] Windows
    - [ ] composer install
    - [ ] .\vendor\bin\phpunit
    - [ ] php -l src/ && php -l tests/
    - [ ] Docker (si applicable)
  - [ ] Linux
    - [ ] composer install
    - [ ] ./vendor/bin/phpunit
    - [ ] php -l src/ && php -l tests/
    - [ ] Docker (si applicable)
