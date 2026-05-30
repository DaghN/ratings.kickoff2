<?php
/**
 * Canonical outbound URLs for join.php (Play & setup). Edit here when links change.
 */
declare(strict_types=1);

/**
 * @return array{
 *   discord: string,
 *   kickoff2_net: string,
 *   kickoff2_com: string,
 *   koa_forum: string,
 *   youtube_tutorial: string,
 *   youtube_wc_playlist: string,
 *   youtube_channel: string,
 *   youtube_promo_embed_id: string,
 *   adapters: list<array{label: string, href: string}>
 * }
 */
function k2_join_page_links(): array
{
    return [
        'discord' => 'https://discord.com/invite/mXcmuE4kzj',
        'kickoff2_net' => 'https://kickoff2.net/',
        'kickoff2_com' => 'https://kickoff2.com/',
        'koa_forum' => 'https://ko-gathering.com/forum',
        'youtube_tutorial' => 'https://www.youtube.com/watch?v=rOunQzfpmGM',
        'youtube_wc_playlist' => 'https://www.youtube.com/watch?v=tEb--soimgs&list=PL_BZxDPPd88YKbQHLod_1EFHPa2iJSIQY',
        'youtube_channel' => 'https://www.youtube.com/@KO2CV_TV',
        // Play & Setup page footer embed (WC playlist clip)
        'youtube_promo_embed_id' => '-OD-f0t92VQ',
        'adapters' => [
            [
                'label' => 'Immortal Joysticks — USB adapter',
                'href' => 'https://www.immortaljoysticks.co.uk/product/usb-adapter/',
            ],
            [
                'label' => 'Sordan.ie Electronics (eBay UK listing)',
                'href' => 'https://www.ebay.co.uk/itm/125627135582',
            ],
            [
                'label' => 'Stepstick.pl — USBJoy (9-pin to USB)',
                'href' => 'https://stepstick.pl/?35,en_usbjoy-adapter-connect-your-atari-amiga-c64-joystick-to-pc!',
            ],
            [
                'label' => 'Monster Joysticks — 9-pin to USB adapter',
                'href' => 'https://monsterjoysticks.com/9-pin-joystick-to-usb-adapter',
            ],
        ],
    ];
}
