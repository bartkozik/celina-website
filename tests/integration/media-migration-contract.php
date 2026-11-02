<?php
/** Run only in a disposable local WordPress installation. */
require_once ABSPATH . 'celina-workbench/content/media.php';
global $wpdb;
function celina_media_test_assert( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
$source = get_stylesheet_directory_uri() . '/assets/images/content/portfolio-image-26.png';
$content = '<!-- wp:gallery {"columns":4} --><figure class="wp-block-gallery has-nested-images columns-4 is-cropped"><!-- wp:image {"sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="' . esc_url( $source ) . '" alt="Person"/><figcaption class="wp-element-caption"><strong>Edited name</strong><br>New role</figcaption></figure><!-- /wp:image --></figure><!-- /wp:gallery --><!-- wp:paragraph --><p>Client edited this copy.</p><!-- /wp:paragraph -->';
$id = wp_insert_post( [ 'post_type'=>'page', 'post_status'=>'draft', 'post_title'=>'Media migration contract', 'post_content'=>wp_slash($content) ] );
$archive = wp_insert_post( [ 'post_type'=>'page', 'post_status'=>'draft', 'post_title'=>'Media migration contract (before redesign)', 'post_content'=>wp_slash($content) ] );
update_post_meta($archive, '_celina_redesign_snapshot_for', $id);
update_post_meta($id, '_celina_applied_content_hash', str_repeat('a',64));
$count = (int) wp_count_posts('attachment')->inherit;
$plan = celina_media_build_plan();
celina_media_test_assert( [] === $plan['errors'], 'Plan has errors: '.wp_json_encode($plan['errors']) );
celina_media_test_assert( $count === (int)wp_count_posts('attachment')->inherit, 'Plan wrote attachments' );
celina_media_test_assert( in_array($id,array_column($plan['updates'],'id'),true), 'Gallery missing from migration plan' );
celina_media_test_assert( !in_array($archive,array_column($plan['updates'],'id'),true), 'Archive must be untouched' );
wp_update_post(['ID'=>$id,'post_content'=>wp_slash($content.'<!-- wp:paragraph --><p>Concurrent edit</p><!-- /wp:paragraph -->')]);
try { celina_media_apply($plan['fingerprint']); throw new RuntimeException('Stale plan accepted'); }
catch (RuntimeException $e) { celina_media_test_assert(str_contains($e->getMessage(),'stale_plan'),'Wrong stale-plan error: '.$e->getMessage()); }
$fresh=celina_media_build_plan();
update_post_meta($id,'_edit_lock',time().':'.get_user_by('login','admin')->ID);
try { celina_media_apply($fresh['fingerprint']); throw new RuntimeException('Locked page accepted'); }
catch (RuntimeException $e) { celina_media_test_assert(str_contains($e->getMessage(),'post_locked'),'Wrong editor-lock error: '.$e->getMessage()); }
delete_post_meta($id,'_edit_lock');
$result=celina_media_apply($fresh['fingerprint']);
$after=get_post($id)->post_content;
celina_media_test_assert(str_contains($after,'Concurrent edit') && str_contains($after,'Edited name') && str_contains($after,'New role'),'Editorial changes lost');
celina_media_test_assert(!str_contains($after,'/themes/celina-child/assets/images/content/'),'Theme photo not migrated');
celina_media_test_assert(str_contains($after,'wp-image-'),'Image ID class missing');
celina_media_test_assert(str_repeat('a',64)===get_post_meta($id,'_celina_applied_content_hash',true),'Edited page hash was blessed');
celina_media_test_assert($content===get_post($archive)->post_content,'Archive changed');
$receipt=get_post_meta($id,'_celina_media_receipt',true);
celina_media_test_assert(is_array($receipt) && get_post($receipt['revision_id'])->post_content === $content.'<!-- wp:paragraph --><p>Concurrent edit</p><!-- /wp:paragraph -->','Recovery revision missing');
$count=(int)wp_count_posts('attachment')->inherit;
$repeat=celina_media_build_plan();
celina_media_test_assert([]===$repeat['imports'] && []===$repeat['updates'],'Migration did not converge');
celina_media_apply($repeat['fingerprint']);
celina_media_test_assert($count===(int)wp_count_posts('attachment')->inherit,'Duplicate imports created');
$map=celina_media_attachment_map();
$imported_id=$map['portfolio-image-26.png']['id'];
$hash=get_post_meta($imported_id,'_celina_media_source_hash',true);
update_post_meta($imported_id,'_celina_media_source_hash',str_repeat('0',64));
$invalid=celina_media_build_plan();
celina_media_test_assert(count($invalid['errors'])>0,'Changed source import silently reused');
update_post_meta($imported_id,'_celina_media_source_hash',$hash);
$duplicate=wp_insert_attachment(['post_title'=>'Duplicate source contract','post_status'=>'inherit','post_mime_type'=>'image/webp'],get_attached_file($imported_id));
add_post_meta($duplicate,'_celina_media_source','portfolio-image-26.png');
$ambiguous=celina_media_build_plan();
celina_media_test_assert(count($ambiguous['errors'])>0,'Ambiguous source attachments silently reused');
// Delete only the synthetic row, never its shared attachment file.
$wpdb->delete($wpdb->postmeta,['post_id'=>$duplicate]); $wpdb->delete($wpdb->posts,['ID'=>$duplicate]); clean_post_cache($duplicate);
$ledger=get_option('_celina_media_imports');
$deleted=wp_insert_attachment(['post_title'=>'Deleted import contract','post_status'=>'inherit','post_mime_type'=>'image/webp']);
$lost=$ledger;
$lost['portfolio-image-26.png']=['id'=>$deleted,'url'=>wp_get_attachment_url($deleted)];
update_option('_celina_media_imports',$lost,false);
wp_delete_attachment($deleted,true);
$missing=celina_media_build_plan();
celina_media_test_assert(count($missing['errors'])>0,'Deleted tracked import silently replaced');
try { celina_media_apply($missing['fingerprint']); throw new RuntimeException('Deleted import accepted'); }
catch (RuntimeException $e) { celina_media_test_assert(str_contains($e->getMessage(),'plan_conflicts'),'Wrong missing-import error'); }
update_option('_celina_media_imports',$ledger,false);
wp_delete_post($id,true); wp_delete_post($archive,true);
WP_CLI::success('Media migration contract passed.');
