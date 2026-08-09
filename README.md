# MAN GO Marketplace

Petits pas pour démarrer et tester l'authentification locale.

## Démarrer le serveur de développement

Depuis la racine du projet (`c:\xampp\htdocs\MAN GO`), lancez le serveur PHP :

```sh
php -S localhost:8000 -t .
```

> Si vous utilisez XAMPP, vous pouvez aussi configurer un hôte virtuel Apache et pointer vers ce dossier.

## Page de connexion (graphique)

Ouvrez votre navigateur et accédez à l'une des URL suivantes :

- http://localhost:8000/login
- http://127.0.0.1:8000/login

La page affichera le formulaire de connexion.

## Identifiants de démonstration (seed_admin)

Le script `database/seed_admin.php` a inséré un compte administrateur :

- Email : `admin@localhost`
- Mot de passe : `190719`

Connectez-vous avec ces identifiants puis changez le mot de passe immédiatement.

## Commandes Git utiles

```sh
# Vérifier l'état
git status

# Ajouter et committer
git add .
git commit -m "Add authentication system, login UI and supporting core" 
```

## Remarques sécurité

- Activez `SESSION_SECURE` et servez l'application via HTTPS en production.
- Configurez un store pour la rate-limit (Redis/APCu) en production.

---

Si vous voulez, je peux aussi :
- pousser le commit sur un remote (`git push`) si vous fournissez l'URL
- exécuter quelques tests `curl` pour valider les endpoints
