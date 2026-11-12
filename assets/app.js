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
