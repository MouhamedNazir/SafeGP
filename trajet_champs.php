<?php
// ============================================================
// Champs du formulaire de trajet (publier.php et modifier_trajet.php)
// Variables attendues :
//   $t        → valeurs à pré-remplir (tableau, éventuellement vide)
//   $contacts_valeurs → numéros WhatsApp du trajet (si publié pour autrui)
//   $avec_kilos_restants → true en modification
// ============================================================
$v = fn($cle) => e($t[$cle] ?? '');
$hhmm = fn($cle) => e(substr((string)($t[$cle] ?? ''), 0, 5));
?>

<h3 class="form-section">Trajet</h3>

<div class="form-row">
    <div class="form-group">
        <label>Pays de départ</label>
        <select name="pays_depart" required><?= options_pays($t['pays_depart'] ?? null) ?></select>
    </div>
    <div class="form-group">
        <label>Ville de départ</label>
        <input type="text" name="depart" value="<?= $v('depart') ?>" placeholder="Ex: Casablanca" required>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Pays d'arrivée</label>
        <select name="pays_arrivee" required><?= options_pays($t['pays_arrivee'] ?? null) ?></select>
    </div>
    <div class="form-group">
        <label>Ville d'arrivée</label>
        <input type="text" name="arrivee" value="<?= $v('arrivee') ?>" placeholder="Ex: Dakar" required>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Date de départ</label>
        <input type="date" name="date_depart" <?= empty($avec_kilos_restants) ? 'min="' . date('Y-m-d') . '"' : '' ?> value="<?= $v('date_depart') ?>" required>
    </div>
    <div class="form-group">
        <label>Heure de départ <span class="facultatif">(facultatif)</span></label>
        <input type="time" name="heure_depart" value="<?= $hhmm('heure_depart') ?>">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Date d'arrivée</label>
        <input type="date" name="date_arrivee" value="<?= $v('date_arrivee') ?>" required>
    </div>
    <div class="form-group">
        <label>Heure d'arrivée <span class="facultatif">(facultatif)</span></label>
        <input type="time" name="heure_arrivee" value="<?= $hhmm('heure_arrivee') ?>">
    </div>
</div>

<h3 class="form-section">Colis</h3>

<div class="form-row">
    <div class="form-group">
        <label>Prix par kilo</label>
        <div class="prix-devise">
            <input type="number" name="prix_kilo" step="0.01" min="0" value="<?= $v('prix_kilo') ?>" placeholder="Ex: 15" required>
            <select name="devise" aria-label="Devise">
                <?php foreach (DEVISES as $code => [$symbole, $libelle]): ?>
                    <option value="<?= $code ?>" <?= ($t['devise'] ?? 'MAD') === $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label>Capacité totale (kg) <span class="facultatif">(facultatif)</span></label>
        <input type="number" name="kilos_total" step="0.1" min="0.1" value="<?= $v('kilos_total') ?>" placeholder="Ex: 23">
    </div>
    <?php if (!empty($avec_kilos_restants)): ?>
    <div class="form-group">
        <label>Kilos encore disponibles</label>
        <input type="number" name="kilos_restants" step="0.1" min="0" value="<?= $v('kilos_restants') ?>" placeholder="Ex: 12">
    </div>
    <?php endif; ?>
</div>

<div class="form-group">
    <label>Date limite de dépôt des colis <span class="facultatif">(facultatif)</span></label>
    <input type="date" name="date_limite_depot" <?= empty($avec_kilos_restants) ? 'min="' . date('Y-m-d') . '"' : '' ?> value="<?= $v('date_limite_depot') ?>">
    <small style="color:var(--texte-doux);">Dernier jour où tu acceptes de recevoir des colis (au plus tard le jour du départ).</small>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Point de dépôt</label>
        <input type="text" name="point_depot" value="<?= $v('point_depot') ?>" placeholder="Ex: Gare de Casa-Voyageurs">
    </div>
    <div class="form-group">
        <label>Horaires de dépôt <span class="facultatif">(facultatif)</span></label>
        <input type="text" name="horaires_depot" value="<?= $v('horaires_depot') ?>" maxlength="100" placeholder="Ex: Lun–Sam, 9h–18h">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Point de réception</label>
        <input type="text" name="point_reception" value="<?= $v('point_reception') ?>" placeholder="Ex: Marché Sandaga, Dakar">
    </div>
    <div class="form-group">
        <label>Horaires de réception <span class="facultatif">(facultatif)</span></label>
        <input type="text" name="horaires_reception" value="<?= $v('horaires_reception') ?>" maxlength="100" placeholder="Ex: 10h–20h">
    </div>
</div>

<div class="form-group">
    <label>Commentaire</label>
    <textarea name="commentaire" placeholder="Ex: Objets fragiles acceptés, pas de liquides..."><?= $v('commentaire') ?></textarea>
</div>

<h3 class="form-section">Contact</h3>

<div class="form-group">
    <label>Numéro de téléphone</label>
    <input type="tel" name="telephone" value="<?= $v('telephone') ?>" placeholder="Ex: +212 6 12 34 56 78" pattern="[\+0-9\s]{8,20}">
</div>

<label class="case-a-cocher">
    <input type="checkbox" name="pour_autrui" value="1" id="pour_autrui" <?= !empty($t['pour_autrui']) ? 'checked' : '' ?>>
    Je publie ce trajet pour quelqu'un d'autre
</label>

<div id="bloc-pour-autrui" class="bloc-encadre" <?= empty($t['pour_autrui']) ? 'hidden' : '' ?>>
    <div class="form-group">
        <label>Nom du voyageur</label>
        <input type="text" name="voyageur_nom" value="<?= $v('voyageur_nom') ?>" maxlength="100" placeholder="Ex: Abdou Diop">
    </div>
    <div class="form-group">
        <label>Numéros WhatsApp pour ce trajet</label>
        <?php include 'contacts_champs.php'; ?>
    </div>
    <small style="color:var(--texte-doux);">Ces numéros remplacent ceux de ton profil sur cette annonce.</small>
</div>
<p class="aide-contact" <?= !empty($t['pour_autrui']) ? 'hidden' : '' ?>>
    <i class="fa-brands fa-whatsapp"></i> Les numéros WhatsApp de <a href="mon_profil.php">ton profil</a> s'afficheront sur l'annonce.
</p>
