// ============================================================
// SafeGP — petits comportements côté navigateur
// (tout fonctionne aussi sans JavaScript, en plus simple)
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ---------- Numéros WhatsApp : ajouter / retirer une ligne ----------
    document.querySelectorAll('.contacts-liste').forEach(function (liste) {
        const max = parseInt(liste.dataset.max || '5', 10);
        const boutonAjouter = liste.nextElementSibling;

        function majBouton() {
            if (boutonAjouter) {
                boutonAjouter.hidden = liste.querySelectorAll('.contact-ligne').length >= max;
            }
        }

        if (boutonAjouter && boutonAjouter.classList.contains('contact-ajouter')) {
            boutonAjouter.addEventListener('click', function () {
                const modele = liste.querySelector('.contact-ligne');
                const nouvelle = modele.cloneNode(true);
                nouvelle.querySelectorAll('input').forEach(function (i) { i.value = ''; });
                liste.appendChild(nouvelle);
                nouvelle.querySelector('input').focus();
                majBouton();
            });
        }

        liste.addEventListener('click', function (e) {
            const bouton = e.target.closest('.contact-retirer');
            if (!bouton) return;
            const lignes = liste.querySelectorAll('.contact-ligne');
            const ligne = bouton.closest('.contact-ligne');
            if (lignes.length > 1) {
                ligne.remove();
            } else {
                // On garde toujours une ligne, on la vide simplement
                ligne.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            }
            majBouton();
        });

        majBouton();
    });

    // ---------- Trajet publié pour quelqu'un d'autre ----------
    const caseAutrui = document.getElementById('pour_autrui');
    const blocAutrui = document.getElementById('bloc-pour-autrui');
    const aideContact = document.querySelector('.aide-contact');
    if (caseAutrui && blocAutrui) {
        caseAutrui.addEventListener('change', function () {
            blocAutrui.hidden = !caseAutrui.checked;
            if (aideContact) aideContact.hidden = caseAutrui.checked;
            const nomVoyageur = blocAutrui.querySelector('input[name="voyageur_nom"]');
            if (nomVoyageur) {
                nomVoyageur.required = caseAutrui.checked;
                if (caseAutrui.checked) nomVoyageur.focus();
            }
        });
    }
});
