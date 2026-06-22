/*
 * Main entrypoint. The SCSS in styles/app.scss is compiled to styles/app.css
 * by symfonycasts/sass-bundle and imported here so AssetMapper bundles it.
 */
import './styles/app.scss';

// Auto-submit the advisories technology filter when it changes.
document.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLSelectElement && target.dataset.autosubmit !== undefined) {
        target.form?.requestSubmit();
    }
});
