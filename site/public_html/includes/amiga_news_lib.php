<?php
/**
 * Amiga News roll — manifest loader and post renderer.
 */
declare(strict_types=1);

function k2_amiga_news_posts_dir(): string
{
    return $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_news/posts';
}

/** @return list<array{id: string, date: string, title: string, file: string, author?: string}> */
function k2_amiga_news_manifest(): array
{
    /** @var list<array{id: string, date: string, title: string, file: string, author?: string}> $rows */
    $rows = require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_news/manifest.php';

    usort($rows, static function (array $a, array $b): int {
        return strcmp($b['date'], $a['date']);
    });

    return $rows;
}

function k2_amiga_news_format_date(string $isoDate): string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
    if ($dt === false) {
        return $isoDate;
    }

    return $dt->format('j F Y');
}

function k2_amiga_news_render_post(array $entry): void
{
    $id = (string) ($entry['id'] ?? '');
    $date = (string) ($entry['date'] ?? '');
    $title = (string) ($entry['title'] ?? '');
    $file = basename((string) ($entry['file'] ?? ''));
    if ($id === '' || $date === '' || $title === '' || $file === '') {
        return;
    }

    $path = k2_amiga_news_posts_dir() . '/' . $file;
    if (!is_file($path)) {
        echo '<article class="k2-card k2-news-post k2-news-post--missing" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';
        echo '<p class="k2-news-post__prose">Post file missing: ' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '</article>';

        return;
    }

    $dateLabel = k2_amiga_news_format_date($date);
    $author = trim((string) ($entry['author'] ?? ''));
    $idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $dateEsc = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
    $dateLabelEsc = htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8');
    $authorEsc = htmlspecialchars($author, ENT_QUOTES, 'UTF-8');
    ?>
<article class="k2-card k2-news-post" id="<?php echo $idEsc; ?>">
	<header class="k2-news-post__head">
		<h2 class="k2-news-post__title"><?php echo $titleEsc; ?></h2>
		<p class="k2-news-post__meta">
			<time class="k2-news-post__date" datetime="<?php echo $dateEsc; ?>"><?php echo $dateLabelEsc; ?></time><?php if ($author !== '') { ?><span class="k2-news-post__meta-sep" aria-hidden="true"> · </span><span class="k2-news-post__author">by <?php echo $authorEsc; ?></span><?php } ?>
		</p>
	</header>
	<div class="k2-news-post__body">
<?php include $path; ?>
	</div>
</article>
<?php
}

function k2_amiga_news_render_roll(): void
{
    $posts = k2_amiga_news_manifest();
    if ($posts === []) {
        echo '<p class="k2-amiga-news-room__empty">No posts yet.</p>';

        return;
    }

    foreach ($posts as $entry) {
        k2_amiga_news_render_post($entry);
    }
}