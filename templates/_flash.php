<?php
$msg = '';
foreach ( array( 'info_message', 'error_message' ) as $mtype ) {
	if ( isset( $_REQUEST[$mtype] ) ) {
		$msg = $_REQUEST[$mtype];
		$msg_type = $mtype == 'info_message' ? 'success' : 'error';
		break;
	}
}
if ( ! empty( $msg ) )
	echo "<div class='$msg_type'>" . esc_html( $msg ) . '</div>';
?>