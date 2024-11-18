<?php

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
    return;
}
?>

<ul>
    <li>First Item</li>
    <li>Second Item</li>
</ul>
