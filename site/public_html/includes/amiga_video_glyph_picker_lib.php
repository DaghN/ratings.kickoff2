<?php

declare(strict_types=1);

/**
 * Dev-only catalog for C06 chronology video glyph comparison.
 * SVG bodies from Iconify (MIT/ISC/Apache — dev picker only).
 */

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

/** @return list<array{id: string, library: string, style: string, body: string, width: int, height: int, stroke: float, fill: string}> */
function amiga_video_glyph_picker_catalog(): array
{
    return [
        ['id' => 'current:film-strip-hand', 'library' => 'Previous (hand strip)', 'style' => 'stroke', 'body' => '<rect x="2.5" y="3.5" width="19" height="17" rx="2.5" /><line x1="7.5" y1="3.5" x2="7.5" y2="20.5" /><line x1="16.5" y1="3.5" x2="16.5" y2="20.5" /><line x1="2.5" y1="12" x2="21.5" y2="12" /><line x1="2.5" y1="7.75" x2="7.5" y2="7.75" /><line x1="2.5" y1="16.25" x2="7.5" y2="16.25" /><line x1="16.5" y1="7.75" x2="21.5" y2="7.75" /><line x1="16.5" y1="16.25" x2="21.5" y2="16.25" />', 'width' => 24, 'height' => 24, 'stroke' => 1.9, 'fill' => 'none'],
        ['id' => 'lucide:clapperboard-prev', 'library' => 'Previous (clapperboard)', 'style' => 'stroke', 'body' => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12.296 3.464l3.02 3.956M20.2 6L3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3zM3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm3.18-5.724l3.1 3.899"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'lucide:clapperboard', 'library' => 'lucide', 'style' => 'stroke', 'body' => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12.296 3.464l3.02 3.956M20.2 6L3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3zM3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm3.18-5.724l3.1 3.899"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'lucide:film', 'library' => 'lucide', 'style' => 'stroke', 'body' => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18M3 7.5h4M3 12h18M3 16.5h4M17 3v18m0-13.5h4m-4 9h4"/></g>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'lucide:video', 'library' => 'lucide', 'style' => 'stroke', 'body' => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m16 13l5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect width="14" height="12" x="2" y="6" rx="2"/></g>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'lucide:circle-play', 'library' => 'lucide', 'style' => 'stroke', 'body' => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M9 9.003a1 1 0 0 1 1.517-.859l4.997 2.997a1 1 0 0 1 0 1.718l-4.997 2.997A1 1 0 0 1 9 14.996z"/><circle cx="12" cy="12" r="10"/></g>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:film-slate', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M216 104H102.09L210 75.51a8 8 0 0 0 5.68-9.84l-8.16-30a15.93 15.93 0 0 0-19.42-11.13L35.81 64.74a15.75 15.75 0 0 0-9.7 7.4a15.5 15.5 0 0 0-1.55 12L32 111.56V200a16 16 0 0 0 16 16h160a16 16 0 0 0 16-16v-88a8 8 0 0 0-8-8m-23.84-64l6 22.07l-22.62 6l-28.12-16.24Zm-66.69 17.6l28.12 16.24l-36.94 9.75l-28.12-16.22Zm-79.4 44.62l-6-22.08l26.5-7L94.69 89.4ZM208 200H48v-80h160z"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:film-strip', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M216 40H40a16 16 0 0 0-16 16v144a16 16 0 0 0 16 16h176a16 16 0 0 0 16-16V56a16 16 0 0 0-16-16M40 88h80v80H40Zm96-16V56h32v16Zm-16 0H88V56h32Zm0 112v16H88v-16Zm16 0h32v16h-32Zm0-16V88h80v80Zm80-96h-32V56h32ZM72 56v16H40V56ZM40 184h32v16H40Zm176 16h-32v-16h32z"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:film-reel', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M232 216h-48.64A103.95 103.95 0 1 0 128 232h104a8 8 0 0 0 0-16M40 128a88 88 0 1 1 88 88a88.1 88.1 0 0 1-88-88m88-24a24 24 0 1 0-24-24a24 24 0 0 0 24 24m0-32a8 8 0 1 1-8 8a8 8 0 0 1 8-8m24 104a24 24 0 1 0-24 24a24 24 0 0 0 24-24m-32 0a8 8 0 1 1 8 8a8 8 0 0 1-8-8m56-24a24 24 0 1 0-24-24a24 24 0 0 0 24 24m0-32a8 8 0 1 1-8 8a8 8 0 0 1 8-8m-96-16a24 24 0 1 0 24 24a24 24 0 0 0-24-24m0 32a8 8 0 1 1 8-8a8 8 0 0 1-8 8"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:video-camera', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M251.77 73a8 8 0 0 0-8.21.39L208 97.05V72a16 16 0 0 0-16-16H32a16 16 0 0 0-16 16v112a16 16 0 0 0 16 16h160a16 16 0 0 0 16-16v-25l35.56 23.71A8 8 0 0 0 248 184a8 8 0 0 0 8-8V80a8 8 0 0 0-4.23-7M192 184H32V72h160zm48-22.95l-32-21.33v-23.44L240 95Z"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:play-circle', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M128 24a104 104 0 1 0 104 104A104.11 104.11 0 0 0 128 24m0 192a88 88 0 1 1 88-88a88.1 88.1 0 0 1-88 88m48.24-94.78l-64-40A8 8 0 0 0 100 88v80a8 8 0 0 0 12.24 6.78l64-40a8 8 0 0 0 0-13.56M116 153.57v-51.14L156.91 128Z"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:film-slate-fill', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M216 104H102.09L210 75.51a8 8 0 0 0 5.68-9.84l-8.16-30a15.93 15.93 0 0 0-19.42-11.13L35.81 64.74a15.75 15.75 0 0 0-9.7 7.4a15.5 15.5 0 0 0-1.55 12L32 111.56V200a16 16 0 0 0 16 16h160a16 16 0 0 0 16-16v-88a8 8 0 0 0-8-8m-23.84-64l6 22.07L164.57 71l-28.13-16.28ZM77.55 70.27l28.12 16.24l-59.6 15.73l-6-22.08Z"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:film-strip-fill', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M216 40H40a16 16 0 0 0-16 16v144a16 16 0 0 0 16 16h176a16 16 0 0 0 16-16V56a16 16 0 0 0-16-16m-32 16h32v16h-32ZM72 200H40v-16h32Zm0-128H40V56h32Zm48 128H88v-16h32Zm0-128H88V56h32Zm48 128h-32v-16h32Zm0-128h-32V56h32Zm48 128h-32v-16h32z"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:film-reel-fill', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M232 216h-48.64A103.95 103.95 0 1 0 128 232h104a8 8 0 0 0 0-16M80 148a20 20 0 1 1 20-20a20 20 0 0 1-20 20m48 48a20 20 0 1 1 20-20a20 20 0 0 1-20 20m0-96a20 20 0 1 1 20-20a20 20 0 0 1-20 20m28 28a20 20 0 1 1 20 20a20 20 0 0 1-20-20"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:video-camera-fill', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M192 72v112a16 16 0 0 1-16 16H32a16 16 0 0 1-16-16V72a16 16 0 0 1 16-16h144a16 16 0 0 1 16 16m58 .25a8.23 8.23 0 0 0-6.63 1.22l-33.59 22.39a4 4 0 0 0-1.78 3.33v57.62a4 4 0 0 0 1.78 3.33l33.78 22.52a8 8 0 0 0 8.58.19a8.33 8.33 0 0 0 3.86-7.17V80a8 8 0 0 0-6-7.75"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:play-circle-fill', 'library' => 'ph', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M128 24a104 104 0 1 0 104 104A104.11 104.11 0 0 0 128 24m40.55 110.58l-52 36A8 8 0 0 1 104 164V92a8 8 0 0 1 12.55-6.58l52 36a8 8 0 0 1 0 13.16"/>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ph:film-slate-duotone', 'library' => 'ph', 'style' => 'duotone', 'body' => '<g fill="currentColor"><path d="m67.71 64.59l47.79 27.6L40.43 112l-8.16-30a7.76 7.76 0 0 1 5.58-9.52Zm132.13-26.83a7.9 7.9 0 0 0-9.66-5.49l-63.57 16.78l47.79 27.59l33.6-8.87Z" opacity=".2"/><path d="M216 104H102.09L210 75.51a8 8 0 0 0 5.68-9.84l-8.16-30a15.93 15.93 0 0 0-19.42-11.13L35.81 64.74a15.75 15.75 0 0 0-9.7 7.4a15.5 15.5 0 0 0-1.55 12L32 111.56V200a16 16 0 0 0 16 16h160a16 16 0 0 0 16-16v-88a8 8 0 0 0-8-8m-23.84-64l6 22.07l-22.62 6l-28.12-16.24Zm-66.69 17.6l28.12 16.24l-36.94 9.75l-28.12-16.22Zm-79.4 44.62l-6-22.08l26.5-7L94.69 89.4ZM208 200H48v-80h160z"/></g>', 'width' => 256, 'height' => 256, 'stroke' => 0, 'fill' => ''],
        ['id' => 'tabler:movie', 'library' => 'tabler', 'style' => 'stroke', 'body' => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm4-2v16m8-16v16M4 8h4m-4 8h4m-4-4h16m-4-4h4m-4 8h4"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'tabler:video', 'library' => 'tabler', 'style' => 'stroke', 'body' => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 10l4.553-2.276A1 1 0 0 1 21 8.618v6.764a1 1 0 0 1-1.447.894L15 14zM3 8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'tabler:player-play-filled', 'library' => 'tabler', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M6 4v16a1 1 0 0 0 1.524.852l13-8a1 1 0 0 0 0-1.704l-13-8A1 1 0 0 0 6 4"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'tabler:brand-youtube-filled', 'library' => 'tabler', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M18 3a5 5 0 0 1 5 5v8a5 5 0 0 1-5 5H6a5 5 0 0 1-5-5V8a5 5 0 0 1 5-5zM9 9v6a1 1 0 0 0 1.514.857l5-3a1 1 0 0 0 0-1.714l-5-3A1 1 0 0 0 9 9"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ri:film-line', 'library' => 'ri', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M2 3.993A1 1 0 0 1 2.992 3h18.016c.548 0 .992.445.992.993v16.014a1 1 0 0 1-.992.993H2.992A.993.993 0 0 1 2 20.007zM8 5v14h8V5zM4 5v2h2V5zm14 0v2h2V5zM4 9v2h2V9zm14 0v2h2V9zM4 13v2h2v-2zm14 0v2h2v-2zM4 17v2h2v-2zm14 0v2h2v-2z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ri:film-fill', 'library' => 'ri', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M2 3.993A1 1 0 0 1 2.992 3h18.016c.548 0 .992.445.992.993v16.014a1 1 0 0 1-.992.993H2.992A.993.993 0 0 1 2 20.007zM4 5v2h2V5zm14 0v2h2V5zM4 9v2h2V9zm14 0v2h2V9zM4 13v2h2v-2zm14 0v2h2v-2zM4 17v2h2v-2zm14 0v2h2v-2z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ri:movie-line', 'library' => 'ri', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M2 3.993A1 1 0 0 1 2.992 3h18.016c.548 0 .992.445.992.993v16.014a1 1 0 0 1-.992.993H2.992A.993.993 0 0 1 2 20.007zM4 5v14h16V5zm6.622 3.415l4.879 3.252a.4.4 0 0 1 0 .666l-4.88 3.252a.4.4 0 0 1-.621-.332V8.747a.4.4 0 0 1 .622-.332"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'ri:movie-fill', 'library' => 'ri', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M2 3.993A1 1 0 0 1 2.992 3h18.016c.548 0 .992.445.992.993v16.014a1 1 0 0 1-.992.993H2.992A.993.993 0 0 1 2 20.007zm8.622 4.422a.4.4 0 0 0-.622.332v6.506a.4.4 0 0 0 .622.332l4.879-3.252a.4.4 0 0 0 0-.666z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'heroicons:film-solid', 'library' => 'heroicons', 'style' => 'fill', 'body' => '<path fill="currentColor" fill-rule="evenodd" d="M1.5 5.625c0-1.036.84-1.875 1.875-1.875h17.25c1.035 0 1.875.84 1.875 1.875v12.75c0 1.035-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 0 1 1.5 18.375zm1.5 0v1.5c0 .207.168.375.375.375h1.5a.375.375 0 0 0 .375-.375v-1.5a.375.375 0 0 0-.375-.375h-1.5A.375.375 0 0 0 3 5.625m16.125-.375a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h1.5A.375.375 0 0 0 21 7.125v-1.5a.375.375 0 0 0-.375-.375zM21 9.375A.375.375 0 0 0 20.625 9h-1.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h1.5a.375.375 0 0 0 .375-.375zm0 3.75a.375.375 0 0 0-.375-.375h-1.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h1.5a.375.375 0 0 0 .375-.375zm0 3.75a.375.375 0 0 0-.375-.375h-1.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h1.5a.375.375 0 0 0 .375-.375zM4.875 18.75a.375.375 0 0 0 .375-.375v-1.5a.375.375 0 0 0-.375-.375h-1.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375zM3.375 15h1.5a.375.375 0 0 0 .375-.375v-1.5a.375.375 0 0 0-.375-.375h-1.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375m0-3.75h1.5a.375.375 0 0 0 .375-.375v-1.5A.375.375 0 0 0 4.875 9h-1.5A.375.375 0 0 0 3 9.375v1.5c0 .207.168.375.375.375m4.125 0a.75.75 0 0 0 0 1.5h9a.75.75 0 0 0 0-1.5z" clip-rule="evenodd"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'heroicons:video-camera-solid', 'library' => 'heroicons', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M4.5 4.5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h8.25a3 3 0 0 0 3-3v-9a3 3 0 0 0-3-3zm15.44 14.25l-2.69-2.69V7.94l2.69-2.69c.944-.945 2.56-.276 2.56 1.06v11.38c0 1.336-1.616 2.005-2.56 1.06"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'heroicons:play-circle-solid', 'library' => 'heroicons', 'style' => 'fill', 'body' => '<path fill="currentColor" fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75s-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12m14.024-.983a1.125 1.125 0 0 1 0 1.967l-5.603 3.112A1.125 1.125 0 0 1 9 15.113V8.887c0-.857.922-1.4 1.671-.983z" clip-rule="evenodd"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'mdi:movie-open', 'library' => 'mdi', 'style' => 'fill', 'body' => '<path fill="currentColor" d="m20.84 2.18l-3.93.78l2.74 3.54l1.97-.4zm-6.87 1.36L12 3.93l2.75 3.53l1.96-.39zm-4.9.96l-1.97.41l2.75 3.53l1.96-.39zm-4.91 1l-.98.19a2 2 0 0 0-1.57 2.35L2 10l4.9-.97zM2 10v10a2 2 0 0 0 2 2h16c1.11 0 2-.89 2-2V10z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'mdi:movie-open-outline', 'library' => 'mdi', 'style' => 'fill', 'body' => '<path fill="currentColor" d="m20.84 2.18l-3.93.78l2.74 3.54l1.97-.4zm-6.87 1.36L12 3.93l2.75 3.53l1.96-.39zm-4.9.96l-1.97.41l2.75 3.53l1.96-.39zm-4.91 1l-.98.19a1.995 1.995 0 0 0-1.57 2.35L2 10l4.9-.97zM20 12v8H4v-8zm2-2H2v10a2 2 0 0 0 2 2h16c1.11 0 2-.89 2-2z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'mdi:filmstrip', 'library' => 'mdi', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M18 9h-2V7h2m0 6h-2v-2h2m0 6h-2v-2h2M8 9H6V7h2m0 6H6v-2h2m0 6H6v-2h2M18 3v2h-2V3H8v2H6V3H4v18h2v-2h2v2h8v-2h2v2h2V3z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'mdi:film', 'library' => 'mdi', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M3.5 3H5V1.8c0-.44.36-.8.8-.8h4.4c.44 0 .8.36.8.8V3h1.5A1.5 1.5 0 0 1 14 4.5V5h8v15h-8v.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 20.5v-16A1.5 1.5 0 0 1 3.5 3M18 7v2h2V7zm-4 0v2h2V7zm-4 0v2h2V7zm4 9v2h2v-2zm4 0v2h2v-2zm-8 0v2h2v-2z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'solar:clapperboard-bold', 'library' => 'solar', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M10.096 2.004c-3.474.027-5.38.208-6.632 1.46c-.857.858-1.213 2.022-1.36 3.786H6.6zM2.026 8.75C2 9.689 2 10.763 2 12c0 4.714 0 7.071 1.464 8.535C4.93 22 7.286 22 12 22s7.071 0 8.535-1.465C22 19.072 22 16.714 22 12c0-1.237 0-2.311-.026-3.25zm19.87-1.5c-.147-1.764-.503-2.928-1.36-3.786c-.598-.597-1.344-.95-2.337-1.16L14.9 7.25zM16.54 2.088C15.33 2 13.845 2 12 2h-.099l-3.5 5.25H13.1z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'solar:clapperboard-bold-duotone', 'library' => 'solar', 'style' => 'duotone', 'body' => '<path fill="currentColor" d="M10.096 2.004c-3.474.027-5.38.208-6.632 1.46c-.857.858-1.213 2.022-1.36 3.786h4.494zm11.8 5.246c-.148-1.764-.503-2.928-1.36-3.786c-.598-.597-1.344-.95-2.338-1.16L14.901 7.25zM16.54 2.088C15.33 2 13.845 2 12 2h-.1L8.4 7.25h4.697z"/><path fill="currentColor" d="M2.026 8.75C2 9.689 2 10.763 2 12c0 4.714 0 7.071 1.464 8.535C4.93 22 7.286 22 12 22s7.071 0 8.535-1.465C22 19.072 22 16.714 22 12c0-1.237 0-2.311-.026-3.25z" opacity=".5"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'solar:videocamera-record-bold', 'library' => 'solar', 'style' => 'fill', 'body' => '<path fill="currentColor" fill-rule="evenodd" d="M2 12.5v-1c0-3.287 0-4.931.908-6.038a4 4 0 0 1 .554-.554C4.57 4 6.212 4 9.5 4c3.287 0 4.931 0 6.038.908a4 4 0 0 1 .554.554c.702.855.861 2.031.897 4.038l.67-.33c1.945-.972 2.918-1.459 3.63-1.019S22 9.68 22 11.854v.292c0 2.175 0 3.263-.711 3.703c-.712.44-1.685-.047-3.63-1.02l-.67-.329c-.036 2.007-.195 3.183-.897 4.038a4 4 0 0 1-.554.554C14.43 20 12.788 20 9.5 20c-3.287 0-4.931 0-6.038-.908a4 4 0 0 1-.554-.554C2 17.43 2 15.788 2 12.5m11.56-2.94a1.5 1.5 0 1 0-2.121-2.12a1.5 1.5 0 0 0 2.122 2.12" clip-rule="evenodd"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'material-symbols:movie', 'library' => 'material-symbols', 'style' => 'fill', 'body' => '<path fill="currentColor" d="m4 4l2 4h3L7 4h2l2 4h3l-2-4h2l2 4h3l-2-4h3q.825 0 1.413.588T22 6v12q0 .825-.587 1.413T20 20H4q-.825 0-1.412-.587T2 18V6q0-.825.588-1.412T4 4"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'material-symbols:movie-rounded', 'library' => 'material-symbols', 'style' => 'fill', 'body' => '<path fill="currentColor" d="m4 4l1.625 3.25q.175.35.5.55t.7.2q.75 0 1.15-.638t.05-1.312L7 4h2l1.625 3.25q.175.35.5.55t.7.2q.75 0 1.15-.638t.05-1.312L12 4h2l1.625 3.25q.175.35.5.55t.7.2q.75 0 1.15-.638t.05-1.312L17 4h3q.825 0 1.413.587T22 6v12q0 .825-.587 1.413T20 20H4q-.825 0-1.412-.587T2 18V6q0-.825.588-1.412T4 4"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'material-symbols:smart-display', 'library' => 'material-symbols', 'style' => 'fill', 'body' => '<path fill="currentColor" d="m9.5 16.5l7-4.5l-7-4.5zM4 20q-.825 0-1.412-.587T2 18V6q0-.825.588-1.412T4 4h16q.825 0 1.413.588T22 6v12q0 .825-.587 1.413T20 20z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'iconoir:media-video', 'library' => 'iconoir', 'style' => 'stroke', 'body' => '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M21 3.6v16.8a.6.6 0 0 1-.6.6H3.6a.6.6 0 0 1-.6-.6V3.6a.6.6 0 0 1 .6-.6h16.8a.6.6 0 0 1 .6.6"/><path d="M9.898 8.513a.6.6 0 0 0-.898.52v5.933a.6.6 0 0 0 .898.521l5.19-2.966a.6.6 0 0 0 0-1.042z"/></g>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'iconoir:play-solid', 'library' => 'iconoir', 'style' => 'fill', 'body' => '<path fill="currentColor" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.906 4.537A.6.6 0 0 0 6 5.053v13.894a.6.6 0 0 0 .906.516l11.723-6.947a.6.6 0 0 0 0-1.032z"/>', 'width' => 24, 'height' => 24, 'stroke' => 0, 'fill' => ''],
        ['id' => 'fluent:video-clip-24-regular', 'library' => 'fluent', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M2 6.25A3.25 3.25 0 0 1 5.25 3h13.5A3.25 3.25 0 0 1 22 6.25v11.5A3.25 3.25 0 0 1 18.75 21H5.25A3.25 3.25 0 0 1 2 17.75zM5.25 4.5A1.75 1.75 0 0 0 3.5 6.25v11.5c0 .966.784 1.75 1.75 1.75h13.5a1.75 1.75 0 0 0 1.75-1.75V6.25a1.75 1.75 0 0 0-1.75-1.75zM9 9.25v5.5a1 1 0 0 0 1.482.876l5-2.75a1 1 0 0 0 0-1.752l-5-2.75A1 1 0 0 0 9 9.251"/>', 'width' => 20, 'height' => 20, 'stroke' => 0, 'fill' => ''],
        ['id' => 'fluent:video-24-regular', 'library' => 'fluent', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M5.25 5A3.25 3.25 0 0 0 2 8.25v7.5A3.25 3.25 0 0 0 5.25 19h7.5A3.25 3.25 0 0 0 16 15.75v-.312l3.258 2.25c1.16.8 2.744-.03 2.744-1.44V7.751c0-1.41-1.584-2.242-2.744-1.44L16 8.562V8.25A3.25 3.25 0 0 0 12.75 5zM16 10.384l4.11-2.838a.25.25 0 0 1 .392.206v8.495a.25.25 0 0 1-.392.206L16 13.615zM3.5 8.25c0-.966.784-1.75 1.75-1.75h7.5c.966 0 1.75.784 1.75 1.75v7.5a1.75 1.75 0 0 1-1.75 1.75h-7.5a1.75 1.75 0 0 1-1.75-1.75z"/>', 'width' => 20, 'height' => 20, 'stroke' => 0, 'fill' => ''],
        ['id' => 'game-icons:clapperboard', 'library' => 'game-icons', 'style' => 'fill', 'body' => '<path fill="currentColor" d="m419.682 26.2l-8.66 2.452L32.915 135.81L55.27 214.7l386.77-109.608zm-12.41 22.224l9.074 32.014l-41.086-22.942zM350.77 64.438l56.8 31.714l-37.084 10.51l-56.8-31.715l37.084-10.51zm-61.577 17.45l56.803 31.716l-37.084 10.51l-56.8-31.718l37.08-10.51zm-61.574 17.45l56.802 31.715l-37.084 10.51l-56.803-31.715l37.084-10.51zm-61.577 17.45l56.803 31.716l-37.084 10.51l-56.8-31.717l37.08-10.51zm-61.574 17.45l56.8 31.715l-37.083 10.51l-56.802-31.715l37.084-10.51zm-45.86 26.227l41.085 22.94l-32.01 9.072zM55 215v274h402V215zm18 18h33.273L73 266.273zm58.727 0h38.546l-46 46H85.727zm64 0h38.546l-46 46h-38.546zm64 0h38.546l-46 46h-38.546zm64 0h38.546l-46 46h-38.546zm64 0h38.546l-46 46h-38.546zM439 245.727V279h-33.273zM73 297h366v174H73zm248.635 46.57l-192.44.703l.067 18l192.44-.703zM130.7 391.33l-.134 17.998l92.707.703l.137-18zm127.155.7l-.2 18l63.913.702l.2-17.998l-63.913-.703z"/>', 'width' => 512, 'height' => 512, 'stroke' => 0, 'fill' => ''],
        ['id' => 'game-icons:film-projector', 'library' => 'game-icons', 'style' => 'fill', 'body' => '<path fill="currentColor" d="M266 51.727c-39.32 0-71 31.68-71 71s31.68 71.002 71 71.002s71-31.683 71-71.002c0-39.32-31.68-71-71-71m-144 32c-30.483 0-55 24.517-55 55c0 30.482 24.517 55.002 55 55.002s55-24.52 55-55.002s-24.517-55-55-55m-23 128v110.002h238V211.727zm350 4.648l-94 40.285v20.133l94 40.285zm-386 2.352v32h18v-32zm113 121.002v17.998h13.012l-51.123 136.275h19.222l51.507-136.275l.382 136.275h18l.382-136.275l51.507 136.275h19.222l-51.123-136.275H260v-17.998c-28.003-.003-55.997 0-84 0"/>', 'width' => 512, 'height' => 512, 'stroke' => 0, 'fill' => ''],
    ];
}

function amiga_video_glyph_picker_svg(array $glyph, int $size = 16): string
{
    $width = max(1, (int) ($glyph['width'] ?? 24));
    $height = max(1, (int) ($glyph['height'] ?? 24));
    $stroke = (float) ($glyph['stroke'] ?? 0);
    $fill = (string) ($glyph['fill'] ?? '');
    $strokeAttr = $stroke > 0 ? ' stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" stroke-linejoin="round"' : '';
    $fillAttr = $fill !== '' ? ' fill="' . $fill . '"' : '';
    return '<svg class="k2-amiga-video-glyph__icon" xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 ' . $width . ' ' . $height . '"' . $fillAttr . $strokeAttr
        . ' aria-hidden="true" focusable="false">' . (string) ($glyph['body'] ?? '') . '</svg>';
}

function amiga_video_glyph_picker_demo_cell(array $glyph, string $tournamentName, string $hostCountry = 'Italy'): string
{
    $nameLink = '<a class="k2-link-star" href="#">' . k2_h($tournamentName) . '</a>';
    $flag = k2_amiga_country_flag_link($hostCountry);
    $nameHtml = $flag !== '' ? '<span class="k2-amiga-wc-podium-player">' . $flag . $nameLink . '</span>' : $nameLink;
    $svg = amiga_video_glyph_picker_svg($glyph);
    return $nameHtml . '<a class="k2-amiga-video-glyph" href="#" onclick="return false;" aria-hidden="true" tabindex="-1">' . $svg . '</a>';
}

function amiga_video_glyph_picker_render_table(): void
{
    $glyphs = amiga_video_glyph_picker_catalog();
    $demoNames = [
        'World Cup XXIII (Milan)',
        'World Cup XXII (Nottingham)',
        'Nottingham II',
        'Preston I',
        'World Cup XXI (Torremolinos)',
    ];
    $demoCountries = ['Italy', 'England', 'England', 'England', 'Spain'];
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="k2-table k2-table--tournament-index k2-table--video-glyph-picker" data-k2-table="sortable" data-k2-anchor-col="1" data-k2-default-sort="0" data-k2-default-direction="asc" data-k2-skip-initial-sort="1">
<thead><tr>
    <th class="k2-table-cell--right">#</th>
    <th class="k2-table-cell--left">Icon</th>
    <th class="k2-table-cell--left">Library</th>
    <th class="k2-table-cell--left">Style</th>
    <th class="k2-table-cell--right k2-tournament-index-date">Date</th>
    <th class="k2-table-cell--left">Tournament</th>
    <th>Players</th><th>Games</th>
    <th class="k2-table-cell--left">Winner</th>
    <th class="k2-table-cell--left">Format</th>
</tr></thead><tbody class="black">
<?php foreach ($glyphs as $i => $glyph) {
    $name = $demoNames[$i % count($demoNames)];
    $country = $demoCountries[$i % count($demoCountries)];
    $isCurrent = ($glyph['id'] ?? '') === 'ph:play-circle-fill';
    ?>
    <tr<?php echo $isCurrent ? ' class="k2-video-glyph-picker-row--current"' : ''; ?>>
        <td class="k2-table-cell--right"><?php echo (int) ($i + 1); ?></td>
        <td class="k2-table-cell--left"><code class="k2-video-glyph-picker-id"><?php echo k2_h((string) ($glyph['id'] ?? '')); ?></code></td>
        <td class="k2-table-cell--left"><?php echo k2_h((string) ($glyph['library'] ?? '')); ?></td>
        <td class="k2-table-cell--left"><span class="k2-video-glyph-picker-style"><?php echo k2_h((string) ($glyph['style'] ?? '')); ?></span></td>
        <td class="k2-table-cell--right k2-tournament-index-date">Nov 1, 2025</td>
        <td class="k2-table-cell--left"><?php echo amiga_video_glyph_picker_demo_cell($glyph, $name, $country); ?></td>
        <td>37</td><td>331</td>
        <td class="k2-table-cell--left"><?php echo k2_amiga_lb_player_cell(1, 'Dagh N', 'Denmark'); ?></td>
        <td class="k2-table-cell--left"><span class="k2-amiga-tournament-format">World Cup</span></td>
    </tr>
<?php } ?>
</tbody></table>
<?php k2_table_wrap_close(); ?>
    <?php
}
