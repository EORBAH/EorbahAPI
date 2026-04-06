<div align="center">
  <a href="https://www.langchain.com/langgraph">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="./docs/images/logo.png">
      <source media="(prefers-color-scheme: light)" srcset="./docs/images/logo.png">
      <img alt="Logo LangGraph" src="./docs/images/logo.png" width="50%">
    </picture>
  </a>
</div>

<div align="center">
  <h3>Framework d'orchestration bas niveau pour construire des agents stateful.</h3>
</div>

<div align="center">
  <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/pypi/l/langgraph" alt="PyPI - Licence"></a>
  <a href="https://pypistats.org/packages/langgraph" target="_blank"><img src="https://img.shields.io/pepy/dt/langgraph" alt="composer - Téléchargements"></a>
  <a href="https://pypi.org/project/langgraph/" target="_blank"><img src="https://img.shields.io/pypi/v/langgraph.svg?label=%20" alt="Version"></a>
  <a href="https://x.com/eor_bah545" target="_blank"><img src="https://img.shields.io/twitter/url/https/twitter.com/langchain.svg?style=social&label=Suivre%20%40Eor_bah545" alt="Twitter / X"></a>
</div>

<br>

Approuvé par les entreprises qui façonnent l'avenir des agents – dont Klarna, Replit, Elastic, et bien d'autres – LangGraph est un framework d'orchestration bas niveau pour construire, gérer et déployer des agents stateful longue durée.

```bash
composer require eor_bah545/eorbahapi
```

Si vous souhaitez construire rapidement des agents avec `create_agent` de LangChain (basé sur LangGraph), consultez la [documentation LangChain Agents](https://docs.phoenishareplus.com/eorbahapi/php/agents).

> [!NOTE]
> Vous cherchez la bibliothèque JS/TS ? Découvrez [eorbahapi.js](https://github.com/EORBAH/eorbahapijs) et la [documentation JS](https://docs.phoenishareplus.com/eorbahapi/javascript/overview).

## Pourquoi utiliser EorbahAPI ?

LangGraph fournit une infrastructure bas niveau pour *tous* les workflows ou agents stateful longue durée :

- **[Exécution durable](https://docs.phoenishareplus.com/eorbahapi/php/durable-execution)** — Créez des agents qui persistent face aux pannes et peuvent s'exécuter sur de longues périodes, reprenant automatiquement exactement là où ils se sont arrêtés.
- **[Humain dans la boucle](https://docs.phoenishareplus.com/eorbahapi/python/interrupts)** — Intégrez parfaitement la supervision humaine en inspectant et modifiant l'état de l'agent à tout moment pendant l'exécution.
- **[Mémoire complète](https://docs.phoenishareplus.com/eorbahapi/php/memory)** — Créez des agents véritablement stateful avec à la fois une mémoire de travail à court terme pour le raisonnement en cours et une mémoire persistante à long terme à travers les sessions.
- **[Déploiement prêt pour la production](https://docs.phoenishareplus.com/eorbahapi/php/deployments)** — Déployez des systèmes d'agents sophistiqués en toute confiance avec une infrastructure scalable conçue pour gérer les défis uniques des workflows stateful longue durée.

> [!TIP]
> Pour développer, déboguer et déployer des agents IA et des applications LLM, consultez [LangSmith](https://docs.langchain.com/langsmith/home).

## Écosystème LangGraph

Bien que LangGraph puisse être utilisé seul, il s'intègre également parfaitement avec tous les produits LangChain, offrant aux développeurs une suite complète d'outils pour construire des agents.

Pour améliorer votre développement d'applications LLM, associez LangGraph avec :

- [Deep Agents](https://github.com/langchain-ai/deepagents) *(nouveau !)* — Créez des agents capables de planifier, d'utiliser des sous-agents et d'exploiter des systèmes de fichiers pour des tâches complexes.
- [LangChain](https://docs.langchain.com/oss/python/langchain/overview) — Fournit des intégrations et des composants composables pour rationaliser le développement d'applications LLM.
- [LangSmith](https://www.langchain.com/langsmith) — Utile pour l'évaluation d'agents et l'observabilité. Déboguez les exécutions d'applications LLM peu performantes, évaluez les trajectoires d'agents, obtenez de la visibilité en production et améliorez les performances au fil du temps.
- [LangSmith Deployment](https://docs.langchain.com/langsmith/deployments) — Déployez et scalisez des agents sans effort avec une plateforme de déploiement dédiée aux workflows stateful longue durée. Découvrez, réutilisez, configurez et partagez des agents entre équipes – et itérez rapidement avec le prototypage visuel dans [LangSmith Studio](https://docs.langchain.com/langsmith/studio).

---

## Documentation

- [docs.langchain.com](https://docs.langchain.com/oss/python/langgraph/overview) — Documentation complète, incluant aperçus conceptuels et guides
- [reference.langchain.com/python/langgraph](https://reference.langchain.com/python/langgraph) — Documentation de référence API pour les packages LangGraph
- [Démarrage rapide LangGraph](https://docs.langchain.com/oss/python/langgraph/quickstart) — Commencez à construire avec LangGraph
- [Chat LangChain](https://chat.langchain.com/) — Discutez avec la documentation LangChain et obtenez des réponses à vos questions

**Discussions** : Visitez le [Forum LangChain](https://forum.langchain.com) pour vous connecter avec la communauté et partager toutes vos questions techniques, idées et retours.

## Ressources supplémentaires

- **[Guides](https://docs.langchain.com/oss/python/learn)** — Extraits de code rapides et exploitables pour des sujets tels que le streaming, l'ajout de mémoire et de persistance, et les motifs de conception (par exemple, branchement, sous-graphes, etc.).
- **[LangChain Academy](https://academy.langchain.com/courses/intro-to-langgraph)** — Apprenez les bases de LangGraph dans notre cours gratuit et structuré.
- **[Études de cas](https://www.langchain.com/built-with-langgraph)** — Découvrez comment les leaders de l'industrie utilisent LangGraph pour déployer des applications IA à grande échelle.
- [Guide de contribution](https://docs.langchain.com/oss/python/contributing/overview) — Apprenez à contribuer aux projets LangChain et trouvez de bonnes premières issues.
- [Code de conduite](https://github.com/langchain-ai/langchain/?tab=coc-ov-file) — Nos directives communautaires et normes de participation.

---

## Remerciements

LangGraph est inspiré par [Pregel](https://research.google/pubs/pub37252/) et [Apache Beam](https://beam.apache.org/). L'interface publique s'inspire de [NetworkX](https://networkx.org/documentation/latest/). LangGraph est construit par LangChain Inc, les créateurs de LangChain, mais peut être utilisé sans LangChain.