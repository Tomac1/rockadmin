<?php

/**
 * What a field with nothing to show reads as: `CellFormatter` renders a
 * `null` value as empty text (the same rule a grid cell follows -- see
 * `CellFormatter::format()`), and a blank line after a label reads as
 * broken rather than deliberate. This says so plainly instead, for whatever
 * field `field.php` handed off -- nothing here is specific to one field, so
 * nothing is read from it.
 */
?>
<span class="ra-field-missing">&mdash;</span>
