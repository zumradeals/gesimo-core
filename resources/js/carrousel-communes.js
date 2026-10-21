// Défilement manuel : pas de duplication de communes ni de mouvement imposé.
export default () => ({
    debut: true,
    fin: true,
    observation: null,
    init() {
        this.$nextTick(() => {
            this.observation = new ResizeObserver(() => this.actualiser());
            this.observation.observe(this.$refs.piste);
            this.actualiser();
        });
    },
    actualiser() {
        const piste = this.$refs.piste;
        this.debut = piste.scrollLeft <= 2;
        this.fin = piste.scrollLeft + piste.clientWidth >= piste.scrollWidth - 2;
    },
    deplacer(direction) {
        const piste = this.$refs.piste;
        const carte = piste.firstElementChild;
        if (!carte) return;
        const pas = carte.getBoundingClientRect().width + parseFloat(getComputedStyle(piste).columnGap);
        piste.scrollBy({ left: direction * pas, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    },
    destroy() {
        this.observation?.disconnect();
    },
});
