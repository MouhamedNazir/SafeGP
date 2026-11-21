<?php
// ============================================================
// Lignes "nom + numéro WhatsApp" répétables
// Variables attendues : $contacts_valeurs (liste de ['nom','numero'])
// Le bouton "+ Ajouter un numéro" duplique la ligne modèle (script.js).
// ============================================================
$lignes = $contacts_valeurs ?: [['nom' => '', 'numero' => '']];
?>
<div class="contacts-liste" data-max="<?= CONTACTS_MAX ?>">
    <?php foreach ($lignes as $c): ?>
        <div class="contact-ligne">
            <input type="text" name="contact_nom[]" value="<?= e($c['nom']) ?>"
                   placeholder="À qui est ce numéro ? (ex : Moi, Mon frère à Dakar)" maxlength="100">
            <input type="tel" name="contact_numero[]" value="<?= e($c['numero']) ?>"
                   placeholder="Ex : +221 77 123 45 67" pattern="[\+0-9\s]{8,20}">
            <button type="button" class="contact-retirer" title="Retirer ce numéro">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    <?php endforeach; ?>
</div>
<button type="button" class="contact-ajouter">
    <i class="fa-solid fa-plus"></i> Ajouter un numéro
</button>
<small style="color:var(--texte-doux);">Jusqu'à <?= CONTACTS_MAX ?> numéros. Chaque numéro s'affiche avec le nom de son propriétaire.</small>
