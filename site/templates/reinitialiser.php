<section class="carte etroite" aria-labelledby="titre-reset">
    <h1 id="titre-reset">Effacer mon historique ?</h1>
    <p>Toutes tes parties et statistiques seront supprimées (le même fichier sert au quiz du terminal). Cette action est définitive.</p>
    <form method="post" action="/reinitialiser">
        <?= champCsrf() ?>
        <label class="case">
            <input type="checkbox" name="confirmer" value="oui" required>
            Oui, je veux effacer tout mon historique.
        </label>
        <div class="actions">
            <button class="btn danger" type="submit">Effacer définitivement</button>
            <a class="btn" href="/stats">Annuler</a>
        </div>
    </form>
</section>
