// Un seul jeu de champs : colonne sur ordinateur, dialogue natif sur téléphone.
export default () => ({
    ouvert: false,
    init() {
        this.media = window.matchMedia('(max-width: 900px)');
        this.changement = () => this.adapter();
        this.media.addEventListener('change', this.changement);
        this.adapter();
    },
    adapter() {
        this.$refs.panel.close();
        this.ouvert = false;
        if (!this.media.matches) this.$refs.panel.show();
    },
    ouvrir() {
        if (!this.media.matches) return;
        this.$refs.panel.showModal();
        this.ouvert = true;
    },
    fermer() {
        this.$refs.panel.close();
        this.ouvert = false;
        this.$refs.ouvrir.focus();
    },
    ferme() { this.ouvert = false; },
    destroy() { this.media.removeEventListener('change', this.changement); },
});
