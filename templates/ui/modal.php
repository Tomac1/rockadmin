<?php

/**
 * The single shared modal, empty until a later milestone fills it from a
 * fragment's root attributes (data-ra-title, data-ra-size, data-ra-class).
 * One modal for the whole page means an action can open it from anywhere
 * without carrying its own copy of this markup.
 *
 * Two elements, not one. In the design system `.ra-modal` is the white
 * dialog and `.ra-modal-overlay` is the dark sheet behind it; this shipped
 * with `.ra-modal` on the outer element, which made the dialog itself the
 * full-width backdrop and left the sheet undrawn. The outer element keeps
 * the id, because that is the thing core.js shows and hides.
 *
 * `ra-modal--md` is the default width. `.ra-modal` is `width: 100%` and the
 * max-width comes from a size modifier, so a dialog without one is as wide
 * as the window -- the same mistake `.ra-btn` makes about its height, in the
 * same stylesheet. `data-ra-size` on a fragment's root replaces this one.
 *
 * `hidden` is what closes it, and `data-ra-close` is what the close button
 * and the backdrop ask for -- not `data-ra-dismiss`, which removes its
 * target from the document. Removing this one would mean the second action
 * on a page opens nothing, with no error to read.
 */
?>
<div class="ra-modal-overlay ra-modal-backdrop" id="ra-modal" hidden data-ra-modal data-ra-close="modal">
    <div class="ra-modal ra-modal--md ra-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ra-modal-title" tabindex="-1">
        <div class="ra-modal__header ra-modal-header">
            <h2 class="ra-modal__title ra-modal-title" id="ra-modal-title"></h2>
            <button class="ra-modal__close ra-modal-close" type="button" data-ra-close="modal" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>
        <div class="ra-modal__body ra-modal-content"></div>
    </div>
</div>
