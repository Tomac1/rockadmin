<?php

/**
 * The chevron on a `<select>`.
 *
 * `.ra-select` sets `appearance: none`, which takes the browser's own arrow
 * away and reserves 32px on the right for this one instead. A select drawn
 * without it is a box with an empty gap where the arrow should be.
 *
 * It is a partial rather than a background image because that is how the
 * design system draws it -- an absolutely positioned sibling inside
 * `.ra-select-wrap` -- and because it is wanted in three places in two
 * files, which is two places too many to repeat a path in.
 *
 * It reads nothing and takes nothing: there is nothing about one select
 * that would make its chevron different from another's. `currentColor` and
 * the wrapper's position are what fit it to whichever select it sits on.
 */
?>
<span class="ra-form-control-caret ra-select-caret" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4,6 8,10 12,6"/></svg></span>
