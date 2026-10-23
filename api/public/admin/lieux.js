// Fiche destination : la ville choisie dans la recherche de villes (LieuType, data-lieu-remplit) remplit la région,
// le pays et la position sur la carte. Les menus sont créés par EasyAdmin (TomSelect) au chargement de la page.
window.addEventListener('load', () => {
    document.querySelectorAll('select[data-lieu-remplit]').forEach((select) => {
        const tomSelect = select.tomselect;
        if (!tomSelect) {
            return;
        }
        const cibles = JSON.parse(select.dataset.lieuRemplit);
        tomSelect.on('change', (valeur) => {
            const lieu = tomSelect.options[valeur];
            // Une ville déjà enregistrée n'apporte pas de nouvelles informations : rien à recopier.
            if (!lieu || undefined === lieu.pays) {
                return;
            }
            remplir(cibles.region, lieu.region ?? '');
            remplir(cibles.pays, lieu.pays);
            remplir(cibles.latitude, String(lieu.latitude).replace('.', ','));
            remplir(cibles.longitude, String(lieu.longitude).replace('.', ','));
            // Le slug d'EasyAdmin suit la ville tant qu'il est verrouillé.
            select.dispatchEvent(new Event('input'));
        });
    });
});

function remplir(id, valeur) {
    const champ = document.getElementById(id);
    if (!champ) {
        return;
    }
    if (champ.tomselect) {
        champ.tomselect.addOption({ value: valeur, text: valeur });
        champ.tomselect.setValue(valeur);
        return;
    }
    champ.value = valeur;
    champ.dispatchEvent(new Event('input'));
}
