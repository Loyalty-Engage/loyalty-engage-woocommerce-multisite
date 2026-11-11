<?php

define('ABSPATH', __DIR__);
$blog = 1;
$stack = [];
$activated = [];
function get_current_network_id() { return 1; }
function get_sites($args) { return array_slice([1, 2, 3], $args['offset'], $args['number']); }
function switch_to_blog($id) { $GLOBALS['stack'][] = $GLOBALS['blog']; $GLOBALS['blog'] = $id; }
function restore_current_blog() { $GLOBALS['blog'] = array_pop($GLOBALS['stack']); }
function current_user_can($cap) { return false; }
function wp_die($message) { throw new RuntimeException($message); }
class LEWM_Activator {
    public static function activate() { $GLOBALS['activated'][] = $GLOBALS['blog']; }
}
require_once dirname(__DIR__) . '/includes/class-lewm-network.php';
LEWM_Network::each_site([LEWM_Activator::class, 'activate']);
if ($activated !== [1, 2, 3] || $blog !== 1 || $stack !== []) {
    throw new RuntimeException('Existing-site initialization must restore the original blog.');
}
LEWM_Network::initialize_site((object) ['site_id' => 1, 'blog_id' => 4]);
if ($activated !== [1, 2, 3, 4] || $blog !== 1) {
    throw new RuntimeException('New network sites must initialize with the correct site context.');
}
LEWM_Network::initialize_site((object) ['site_id' => 2, 'blog_id' => 5]);
if (count($activated) !== 4) { throw new RuntimeException('Other networks must not be initialized.'); }
try {
    LEWM_Network::each_site(static function () { throw new LogicException('Simulated schema error'); });
} catch (LogicException $expected) {}
if ($blog !== 1 || $stack !== []) { throw new RuntimeException('Site context must be restored after an error.'); }
try {
    LEWM_Network::save();
    throw new LogicException('Shop administrators must not save network credentials.');
} catch (RuntimeException $expected) {
    if ($expected->getMessage() !== 'Geen toegang.') { throw $expected; }
}
echo "Network lifecycle and admin authorization tests passed.\n";
