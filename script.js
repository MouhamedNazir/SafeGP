// ============================================================
// SafeGP — petits comportements côté navigateur
// (tout fonctionne aussi sans JavaScript, en plus simple)
// ============================================================

// Textes traduits (anglais) fournis par la page, sinon français
const T = Object.assign({vu: 'Vu', copie: 'Copié', erreurEnvoi: "Le message n'a pas pu être envoyé : "}, window.SAFEGP_T || {});

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

    // ---------- Recherche : inverser départ et arrivée ----------
    const inverser = document.querySelector('.filtre-inverser');
    if (inverser) {
        inverser.addEventListener('click', function () {
            const f = inverser.form;
            [['pays_depart', 'pays_arrivee'], ['depart', 'arrivee']].forEach(function (paire) {
                const a = f.elements[paire[0]], b = f.elements[paire[1]];
                const tmp = a.value; a.value = b.value; b.value = tmp;
            });
        });
    }

    // ---------- Messagerie : filtrer les conversations ----------
    const filtreConv = document.getElementById('conv-filtre');
    if (filtreConv) {
        const sansAccents = t => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        filtreConv.addEventListener('input', function () {
            const q = sansAccents(filtreConv.value.trim());
            let visibles = 0;
            document.querySelectorAll('.conv-item').forEach(function (item) {
                const ok = !q || (item.dataset.nom || '').includes(q);
                item.hidden = !ok;
                if (ok) visibles++;
            });
            const aucune = document.querySelector('.conv-aucune');
            if (aucune) aucune.hidden = visibles > 0 || !q;
        });
    }

    // ---------- Messagerie : conversation en direct ----------
    const liste = document.getElementById('messages-liste');
    const formMsg = document.getElementById('msg-form');
    if (liste && formMsg) {
        const avec = liste.dataset.with;
        const zone = formMsg.querySelector('textarea');
        let vuJusqua = parseInt(liste.dataset.vu || '0', 10);
        let envoiEnCours = false;

        const echapper = t => String(t).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        const dernierId = () => {
            const msgs = liste.querySelectorAll('.message[data-id]');
            return msgs.length ? parseInt(msgs[msgs.length - 1].dataset.id, 10) : 0;
        };
        const enBas = () => liste.scrollHeight - liste.scrollTop - liste.clientHeight < 80;
        const descendre = () => { liste.scrollTop = liste.scrollHeight; };

        // Indicateur « Vu » sous mon dernier message lu par l'autre
        function majVu() {
            liste.querySelectorAll('.msg-vu').forEach(el => el.remove());
            const lus = Array.from(liste.querySelectorAll('.message.envoye'))
                .filter(el => parseInt(el.dataset.id, 10) <= vuJusqua);
            if (lus.length) {
                lus[lus.length - 1].insertAdjacentHTML('beforeend', '<span class="msg-vu"><i class="fa-solid fa-check-double"></i> ' + T.vu + '</span>');
            }
        }

        function ajouter(m) {
            if (liste.querySelector('.message[data-id="' + m.id + '"]')) return;
            const debut = liste.querySelector('.conv-debut');
            if (debut) debut.remove();
            const derniers = liste.querySelectorAll('.message');
            const dernierJour = derniers.length ? derniers[derniers.length - 1].dataset.jour : null;
            let html = '';
            if (m.jour !== dernierJour) {
                html += '<div class="msg-jour"><span>' + echapper(m.jour) + '</span></div>';
            }
            let trajet = '';
            if (m.trajet) {
                trajet = '<a class="msg-trajet" href="' + echapper(m.trajet.lien) + '">'
                    + '<span class="fi fi-' + echapper(m.trajet.pays[0]) + '"></span> ' + echapper(m.trajet.titre)
                    + ' <span class="fi fi-' + echapper(m.trajet.pays[1]) + '"></span>'
                    + '<small>' + echapper(m.trajet.date) + ' · ' + echapper(m.trajet.prix) + '</small></a>';
            }
            html += '<div class="message ' + (m.moi ? 'envoye' : 'recu') + ' message-nouveau" data-id="' + m.id + '" data-jour="' + echapper(m.jour) + '">'
                + trajet + '<p>' + echapper(m.contenu).replace(/\n/g, '<br>') + '</p>'
                + '<span class="msg-date">' + echapper(m.heure) + '</span></div>';
            liste.insertAdjacentHTML('beforeend', html);
        }

        async function actualiser() {
            try {
                const rep = await fetch('messages_api.php?action=nouveaux&with=' + encodeURIComponent(avec) + '&apres=' + dernierId(), {credentials: 'same-origin'});
                if (!rep.ok) return;
                const data = await rep.json();
                const etaitEnBas = enBas();
                (data.messages || []).forEach(ajouter);
                vuJusqua = data.vu_jusqua || vuJusqua;
                majVu();
                if ((data.messages || []).length && etaitEnBas) descendre();
            } catch (e) { /* réseau indisponible : on réessaiera */ }
        }

        // Envoi sans recharger la page
        formMsg.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (envoiEnCours || !zone.value.trim()) return;
            envoiEnCours = true;
            const donnees = new FormData(formMsg);
            donnees.append('action', 'envoyer');
            try {
                const rep = await fetch('messages_api.php', {method: 'POST', body: donnees, credentials: 'same-origin'});
                const data = await rep.json();
                if (!rep.ok) throw new Error(data.erreur || 'Erreur');
                zone.value = '';
                zone.style.height = '';
                const cite = formMsg.querySelector('.msg-trajet-cite');
                if (cite) cite.remove();
                await actualiser();
                descendre();
            } catch (err) {
                alert(T.erreurEnvoi + err.message);
            } finally {
                envoiEnCours = false;
                zone.focus();
            }
        });

        // Entrée = envoyer, Maj+Entrée = nouvelle ligne ; la zone grandit avec le texte
        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                formMsg.requestSubmit();
            }
        });
        zone.addEventListener('input', function () {
            zone.style.height = 'auto';
            zone.style.height = Math.min(zone.scrollHeight, 140) + 'px';
        });

        const retirer = formMsg.querySelector('.msg-trajet-retirer');
        if (retirer) retirer.addEventListener('click', () => retirer.closest('.msg-trajet-cite').remove());

        majVu();
        descendre();
        // Nouveaux messages toutes les 4 secondes, seulement si l'onglet est visible
        setInterval(function () { if (!document.hidden) actualiser(); }, 4000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) actualiser(); });
    }

    // ---------- Abonnement : copier le numéro de paiement ----------
    document.querySelectorAll('[data-copier]').forEach(function (bouton) {
        bouton.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(bouton.dataset.copier);
                const texte = bouton.innerHTML;
                bouton.innerHTML = '<i class="fa-solid fa-check"></i> ' + T.copie;
                setTimeout(function () { bouton.innerHTML = texte; }, 1500);
            } catch (e) { /* presse-papiers indisponible : le numéro reste affiché */ }
        });
    });

    // ---------- Lien vers un trajet (#trajet-12) caché dans « Trajets passés » ----------
    if (location.hash) {
        const cible = document.getElementById(location.hash.slice(1));
        const repli = cible && cible.closest('details');
        if (repli) {
            repli.open = true;
            cible.scrollIntoView({block: 'start'});
        }
    }

    // ---------- Partage natif (menu « Partager » du téléphone) ----------
    const natif = document.querySelector('.partage-natif');
    if (natif && navigator.share) {
        natif.hidden = false;
        natif.addEventListener('click', function () {
            navigator.share({title: natif.dataset.titre, text: natif.dataset.texte, url: natif.dataset.lien}).catch(function () {});
        });
    }
});

