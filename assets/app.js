import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.css';
import '../src/AcMarche/Mercredi/assets/styles/app.css';
import './styles/app.css';

// CKEditor 5 is only needed on pages that render an editor: load it on demand.
function loadCkeditor() {
    if (document.querySelector('cke5-editor')) {
        import('./vendor/ckeditor5/dist/browser/ckeditor5.css');
        import('@mati365/ckeditor5-symfony');
    }
}

loadCkeditor();
document.addEventListener('turbo:load', loadCkeditor);

// Turbo renders a 5xx response with its own error renderer, which swaps the <head> and
// loses the styles loaded by the importmap. Show the error page in the current document instead.
document.addEventListener('turbo:before-fetch-response', async (event) => {
    const response = event.detail.fetchResponse;
    if (!response.serverError) {
        return;
    }
    event.preventDefault();
    const html = await response.responseHTML;
    if (!html) {
        return;
    }
    const page = new DOMParser().parseFromString(html, 'text/html');
    document.title = page.title;
    document.body.replaceWith(document.adoptNode(page.body));
    window.scrollTo(0, 0);
});
