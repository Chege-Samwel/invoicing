<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Show a shared document that is no longer available.
 *
 * Called from Home when a share token does not resolve: revoked, rotated,
 * deleted, or simply mistyped. show_404() would do, and it is what this used to
 * be - but the person holding the link is not browsing our app, they are a
 * client who was sent an invoice and cannot open it. A bare "404 Page Not Found"
 * tells them nothing, offers nothing, and reflects on the business that sent
 * it.
 *
 * So the page says three things, in this order: what happened, what to do about
 * it, and who can help. Only then, quietly, what we do - this is a client's
 * dead end, not a sales page.
 *
 * @param string $reason short machine code, for logging and for the test suite
 */
if (!function_exists('adomin_render_share_unavailable')) {

    function adomin_render_share_unavailable($reason = 'revoked')
    {
        $CI =& get_instance();

        $CI->output->set_status_header(404);

        $brand = array(
            'name'    => adomin_conf('brand', 'name'),
            'company' => adomin_conf('brand', 'company'),
            'website' => adomin_conf('brand', 'website'),
            'email'   => adomin_conf('brand', 'support_email'),
            'phone'   => adomin_conf('brand', 'support_phone'),
            'accent'  => adomin_conf('brand', 'accent'),
        );

        /*
         * The four things we do, said plainly and in the client's terms rather
         * than ours. Kept short because a person who has just lost access to
         * their invoice is not going to read a brochure.
         */
        $services = array(
            array(
                'icon'  => 'fa-file-text-o',
                'title' => 'Electronic invoicing',
                'body'  => 'Invoices, pro-forma and credit notes, sent as a link with a verification code the client can check.',
            ),
            array(
                'icon'  => 'fa-cubes',
                'title' => 'Stock and stores',
                'body'  => 'Items, quantities and movements, so the invoice and the shelf agree.',
            ),
            array(
                'icon'  => 'fa-link',
                'title' => 'Share links that expire',
                'body'  => 'Every link can be revoked or reissued, which is exactly what happened to this one.',
            ),
            array(
                'icon'  => 'fa-shield',
                'title' => 'Records you can produce',
                'body'  => 'A history of what was issued, who opened it and when it changed.',
            ),
        );

        $reasons = array(
            'revoked' => array(
                'title' => "We can't find that invoice",
                'lead'  => 'The link you followed has been withdrawn by the business that sent it.',
                'body'  => 'That is normal when a document is corrected and reissued, or when a link is deliberately switched off. Nothing is wrong with your invoice - this address simply stopped pointing at it.',
            ),
            'unknown' => array(
                'title' => "We can't find that invoice",
                'lead'  => 'This link does not point at any invoice we have on record.',
                'body'  => 'It may have been cut short when it was copied, or it may belong to a document that has since been deleted.',
            ),
        );
        $copy = isset($reasons[$reason]) ? $reasons[$reason] : $reasons['revoked'];

        include APPPATH . 'views/invoices_parts/share_unavailable.php';
    }
}

if ( ! function_exists('adomin_share_token_user'))
{
	/**
	 * The account id at the front of a share token.
	 *
	 * A token is `<userId>u<random>` - see NewInvoiceModel::ensureShareToken(),
	 * which builds it as USER_ID . 'u' . uniqid(),
	 *
	 *    3u6b0a857c5ef90
	 *
	 * The id and the random part were split with explode('u', $token), which
	 * does not split on "the first u" - it splits on every one. A random part
	 * that happens to contain a u (roughly one token in seven, since uniqid()
	 * is hex) was then cut short, and the lookup used a token that was not the
	 * one in the database:
	 *
	 *     3u6bu7abc9  ->  ['3', '6b', '7abc9']   looked up "6b"
	 *
	 * The page 404s for a link that is perfectly valid, which is why a client
	 * reports "your link does not work" for some invoices and not others.
	 *
	 * @param	string	$token
	 * @return	int	0 when the token is not of this shape
	 */
	function adomin_share_token_user($token)
	{
		$token = (string) $token;
		if ( ! adomin_share_token_is_wellformed($token))
		{
			return 0;
		}
		preg_match('/^(\d+)u/', $token, $match);
		return (int) $match[1];
	}
}

if ( ! function_exists('adomin_share_token_is_wellformed'))
{
	/**
	 * Is this the shape ensureShareToken() produces?
	 *
	 * @param	string	$token
	 * @return	bool
	 */
	function adomin_share_token_is_wellformed($token)
	{
		return (bool) preg_match('/^\d+u[0-9a-z]{4,}$/i', (string) $token);
	}
}
