<?php
/**
 * The Kick Off 2 box art mystery — long-form story page (boxart.php).
 * A nostalgic + funny account of identifying the players, the artist, and the
 * detective work the KO2 online community did on 6 June 2026.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

$img = static function (string $file): string {
    return '/images/boxart/' . $file;
};
?>
<article class="k2-boxart" aria-labelledby="k2-boxart-title">

	<div id="k2-boxart-story" class="k2-boxart-page-anchor" tabindex="-1"></div>

	<header class="k2-boxart__hero">
		<p class="k2-boxart__kicker">Anco · Amiga · 1990 · The cover that started it all</p>
		<h1 id="k2-boxart-title" class="k2-boxart__title">The Kick Off 2 Box Art Mystery</h1>
		<p class="k2-boxart__lede">
			Two footballers frozen in full flight, a wildly over-excited coach in the corner, and a single
			pencil drawing that&rsquo;s been staring back at us from the shelf for 35 years. We always knew the
			cover. We just never knew <em>who</em> was on it. On 6 June 2026, a WhatsApp group of grown adults
			decided that simply would not do any longer.
		</p>

		<figure class="k2-boxart__figure k2-boxart__figure--hero">
			<img src="<?php echo $img('ko2-box-front.png'); ?>" alt="The Kick Off 2 Amiga box art: a white-shirted player about to shoot, a second player kneeling behind him with arms raised in celebration, and a coach pointing." loading="eager" decoding="async" />
			<figcaption>The icon itself. Painted by Cameron Buxton, published by Anco in 1990. Look at it. <em>Really</em> look at it.</figcaption>
		</figure>
	</header>

	<section class="k2-boxart__section">
		<h2 class="k2-boxart__h2">The cast of three</h2>
		<p class="k2-boxart__prose">
			The cover has three characters, and for decades each one was a little question mark:
		</p>
		<ul class="k2-boxart__cast">
			<li><strong>The shooter in white</strong> &mdash; the hero of the composition, one foot planted, the other leg cocked back, about to unleash a shot.</li>
			<li><strong>The man behind him</strong>, sitting back on his shins with arms thrown skyward, celebrating a goal.</li>
			<li><strong>The coach</strong> in the bottom corner, pointing and &mdash; let&rsquo;s be honest &mdash; looking <em>far</em> too thrilled about it all.</li>
		</ul>
		<p class="k2-boxart__prose">
			Three people. One painting. Several lifetimes of Amiga nostalgia. Here&rsquo;s how we finally put names
			to the faces.
		</p>

		<div class="k2-boxart__gallery k2-boxart__gallery--two">
			<figure class="k2-boxart__figure">
				<img src="<?php echo $img('ko2-box-photo.png'); ?>" alt="A real, slightly worn Kick Off 2 boxed copy photographed on a wooden table." loading="lazy" decoding="async" />
				<figcaption>The genuine article &mdash; a boxed copy in the wild, foxing and all.</figcaption>
			</figure>
			<figure class="k2-boxart__figure">
				<img src="<?php echo $img('ko2-box-contents.png'); ?>" alt="The Kick Off 2 box opened up, showing the manual and the blue Amiga disk." loading="lazy" decoding="async" />
				<figcaption>Crack it open: the manual, the blistering-pace marketing copy, and one glorious blue Amiga floppy.</figcaption>
			</figure>
		</div>
	</section>

	<section class="k2-boxart__section">
		<h2 class="k2-boxart__h2">The artist: Cameron Buxton</h2>
		<p class="k2-boxart__prose">
			First, the man behind the brush. The illustration was painted by <strong>Cameron Buxton</strong>, who
			did the cover art for the entire Kick Off and Player Manager range &mdash; data disks and all. And in a
			lovely twist, he came to us. In <strong>2006</strong>, a user called <em>camtheman</em> wandered into
			the <a href="https://ko-gathering.com/forum/viewtopic.php?t=12246" rel="noopener noreferrer">KOA forum</a>
			and dropped this:
		</p>

		<blockquote class="k2-boxart__quote">
			<p>&ldquo;My name is Cameron Buxton and I am the artist who illustrated the original cover art for Kick
			Off 2. Due to a recent happy addition to my family I am now reluctantly putting up for sale this
			original illustration.&rdquo;</p>
			<cite>&mdash; camtheman, KOA forum, June 2006</cite>
		</blockquote>

		<p class="k2-boxart__prose">
			A pencil drawing, 330mm &times; 400mm, framed in silver. When the forum asked the obvious questions &mdash;
			who are the players, and <em>what on earth happened to the main guy&rsquo;s left leg?</em> &mdash; Buxton
			delivered one of the all-time great deadpan replies:
		</p>

		<blockquote class="k2-boxart__quote">
			<p>&ldquo;Yes the illustrations are of actual players and a manager. Who though, I can&rsquo;t remember
			(it was 26 years ago&hellip;). The main guy was the only professional player with one leg shorter than
			the other in the early 90&rsquo;s.&rdquo;</p>
			<cite>&mdash; Cameron Buxton, settling the great leg debate forever</cite>
		</blockquote>

		<p class="k2-boxart__prose">
			&hellip;the thread sat quiet. And then, <strong>six years later</strong>, in walked our own
			<strong>Stainy</strong> with the immortal words:
		</p>

		<blockquote class="k2-boxart__quote k2-boxart__quote--tight">
			<p>&ldquo;I have purchased this excited!!!&rdquo;</p>
			<cite>&mdash; Stainy, 2012, replying to a 2006 thread (&ldquo;6 years!?!&rdquo; &mdash; SimonB)</cite>
		</blockquote>

		<p class="k2-boxart__prose">
			Stainy bought the original drawing straight from the artist. As he relayed it: Buxton did
			<em>all</em> of Anco&rsquo;s games, and only kept the Kick Off 2 piece &ldquo;because it was such a huge
			game.&rdquo; He still owns it today. The forum thread has carried that genuine artwork ever since:
		</p>

		<figure class="k2-boxart__figure k2-boxart__figure--portrait">
			<img src="<?php echo $img('KO2_original_illustration.jpg'); ?>" alt="The genuine original Kick Off 2 cover illustration, framed and signed by Cameron Buxton, photographed in Stainy&#39;s possession." loading="lazy" decoding="async" />
			<figcaption>
				The real original &mdash; Cameron Buxton&rsquo;s signed drawing, framed. No game title, no box stamp:
				just the players, the crowd, and that glorious pencil work.
			</figcaption>
		</figure>

		<p class="k2-boxart__prose">
			You can browse Buxton&rsquo;s full back catalogue of game illustrations in
			<a href="https://vgdensetsu.net/cbuxton/" rel="noopener noreferrer">this lovely gallery</a>. There were
			also earlier forum threads chewing on the same mystery in
			<a href="https://ko-gathering.com/forum/viewtopic.php?t=11377" rel="noopener noreferrer">2005</a> and
			<a href="https://ko-gathering.com/forum/viewtopic.php?t=13567" rel="noopener noreferrer">2007</a>.
		</p>

		<p class="k2-boxart__prose">
			And then there is another scan that turns up from time to time &mdash; honestly, we do not know where
			it came from &mdash; but it opens a lovely question:
		</p>

		<figure class="k2-boxart__figure k2-boxart__figure--portrait">
			<img src="<?php echo $img('original-illustration.png'); ?>" alt="A scan of Kick Off 2 cover artwork with game title lettering and a signature in the lower-left corner." loading="lazy" decoding="async" />
			<figcaption>
				The box prototype? The signature in the bottom-left corner really looks like
				<strong>Dino Dini&rsquo;s</strong> artful hand &mdash; the game&rsquo;s creator, not the illustrator.
				Plausible as a production-ready comp before the Anco stamp and the last small tweaks hit the box?
			</figcaption>
		</figure>
	</section>

	<section class="k2-boxart__section">
		<h2 id="k2-boxart-hugo" class="k2-boxart__h2">The man in the background: Hugo Sánchez</h2>
		<p class="k2-boxart__prose">
			Now to the players &mdash; and this is where the goosebumps start. The figure behind the shooter,
			sitting back on his shins with both arms reaching for the sky, is
			<a href="https://en.wikipedia.org/wiki/Hugo_S%C3%A1nchez" rel="noopener noreferrer">Hugo
			Sánchez</a>, the legendary Mexican striker. If you grew up with this box on the shelf, finding the
			source photograph for the first time is one of those small earthquakes: a World Cup moment you may never
			have seen, suddenly welded to a painting you&rsquo;ve known since childhood.
		</p>

		<figure class="k2-boxart__figure">
			<img src="<?php echo $img('hugo-sanchez-1986.png'); ?>" alt="Hugo Sánchez of Mexico on his knees with arms raised during the 1986 World Cup." loading="lazy" decoding="async" />
			<figcaption>
				The source: Hugo Sánchez, Mexico v Paraguay, 1986 FIFA World Cup, Aztec Stadium, 7 June 1986.
				Same raised arms. Same skyward gaze. Same celebration on his shins. (Photo: Allsport/Hulton Archive/Getty Images.)
			</figcaption>
		</figure>

		<p class="k2-boxart__prose">
			Match the arms to the cover and there&rsquo;s no doubt &mdash; it&rsquo;s him, lifted almost pose-for-pose
			from Mexico at the &rsquo;86 World Cup. One of the most iconic covers in Amiga history, and here is Hugo
			Sánchez in the flesh, frozen in the exact moment Buxton borrowed. If that doesn&rsquo;t make you grin,
			we honestly don&rsquo;t know what will.
		</p>
	</section>

	<section class="k2-boxart__section">
		<h2 class="k2-boxart__h2">The man in the foreground: the long hunt</h2>
		<p class="k2-boxart__prose">
			The hero in white &mdash; the Palace-sashed striker about to let fly &mdash; was clearly wearing a
			<strong>Crystal Palace</strong> kit. But <em>which</em> Palace player? This is where the WhatsApp group went full true-crime corkboard,
			red string and all.
		</p>

		<p class="k2-boxart__prose">
			Our member <strong>Steve B</strong> turned up a press photograph that matched the pose beautifully &mdash;
			and it was even <em>for sale</em>, complete with the original copyright stamp on the back. He bought it
			on the spot before anyone else could swoop in. 😄
		</p>

		<div class="k2-boxart__gallery k2-boxart__gallery--two">
			<figure class="k2-boxart__figure">
				<img src="<?php echo $img('palace-player-bw.png'); ?>" alt="Black-and-white press photo of a Crystal Palace player striking the ball, in the same pose as the box art." loading="lazy" decoding="async" />
				<figcaption>Steve B&rsquo;s prize: the press photo. That cocked leg. That arm. Look familiar?</figcaption>
			</figure>
			<figure class="k2-boxart__figure">
				<img src="<?php echo $img('press-stamp-ian-wright.png'); ?>" alt="The back of the press photo with a Universal Pictorial Press stamp, labelled Ian Wright, Crystal Palace, April 1987." loading="lazy" decoding="async" />
				<figcaption>The back of the photo&hellip; labelled <strong>&ldquo;IAN WRIGHT, Crystal Palace, Apr 1987.&rdquo;</strong> Case closed? Not so fast.</figcaption>
			</figure>
		</div>

		<p class="k2-boxart__prose">
			Ian Wright! Famous, plausible, Palace through and through. Except&hellip; something smelled off. The faces
			didn&rsquo;t <em>quite</em> line up. Then <strong>Mike C</strong> produced a photo of a different Palace
			player &mdash; one <strong>Andy Gray</strong> &mdash; and the resemblance was uncanny.
		</p>

		<figure class="k2-boxart__figure k2-boxart__figure--chat">
			<img src="<?php echo $img('whatsapp-mikec.png'); ?>" alt="WhatsApp message from Mike C with a colour photo of Andy Gray running with the ball, captioned 'Andy Gray looks good - even has the same boots'." loading="lazy" decoding="async" />
			<figcaption>Mike C lands the first body blow: &ldquo;Andy Gray looks good &mdash; even has the same boots.&rdquo;</figcaption>
		</figure>

		<p class="k2-boxart__prose">
			The suspicion deepened when Steve B dug up another shot of Andy Gray whose right-hand gesture was a
			dead ringer for the cover. At this point a wonderful, slightly unhinged theory was floated in the group
			chat:
		</p>

		<figure class="k2-boxart__figure k2-boxart__figure--chat">
			<img src="<?php echo $img('whatsapp-steveb-spurs.png'); ?>" alt="WhatsApp messages from Steve B wondering whether his photo was mislabelled, sharing an Andy Gray Spurs photo, and joking about footballers' hand gestures." loading="lazy" decoding="async" />
			<figcaption>
				Steve B: &ldquo;Or is it some weird phenomenon when you play football to a high level you end up with
				the same hand gesture when you shoot!?&rdquo;
			</figcaption>
		</figure>

		<p class="k2-boxart__prose">
			Mike C then did the sensible thing and took it to the people who would know best &mdash; the
			<a href="https://www.reddit.com/r/crystalpalace/comments/1tyfm4k/id_of_player_if_possible_please/" rel="noopener noreferrer">r/crystalpalace</a>
			subreddit. The verdict came back fast and decisive.
		</p>

		<div class="k2-boxart__gallery k2-boxart__gallery--two">
			<figure class="k2-boxart__figure">
				<img src="<?php echo $img('reddit-replies.png'); ?>" alt="Reddit notifications showing two replies to a post titled 'Andy Gray' in r/crystalpalace." loading="lazy" decoding="async" />
				<figcaption>The Palace faithful reply within minutes. Title of the answer: &ldquo;Andy Gray.&rdquo;</figcaption>
			</figure>
			<figure class="k2-boxart__figure">
				<img src="<?php echo $img('reddit-anco-sidestory.png'); ?>" alt="A Reddit comment joking that the figure wasn't officially Andy Gray so Anco didn't have to pay him, but it totally was." loading="lazy" decoding="async" />
				<figcaption>
					&hellip;and a glorious side-story: &ldquo;Yeah, wasn&rsquo;t officially Andy Gray, so ANCO didn&rsquo;t
					have to pay him. But it totally was.&rdquo;
				</figcaption>
			</figure>
		</div>

		<p class="k2-boxart__prose">
			So the press photo Steve B bought was almost certainly <em>mislabelled</em> as Ian Wright. The man on the
			Kick Off 2 box is <a href="https://en.wikipedia.org/wiki/Andy_Gray_(footballer,_born_1964)" rel="noopener noreferrer">Andy
			Gray</a> &mdash; the Crystal Palace one, born 1964, not the Sky Sports commentator.
		</p>

		<figure class="k2-boxart__figure">
			<img src="<?php echo $img('andy-gray-running-color.png'); ?>" alt="Colour action photo of Andy Gray of Crystal Palace running with the ball." loading="lazy" decoding="async" />
			<figcaption>Andy Gray in full Crystal Palace flight. The boots, the build, the gait &mdash; it all checks out.</figcaption>
		</figure>

		<p class="k2-boxart__prose">
			And then came the moment that put it beyond doubt. Steve B dropped the cover and that very press photo
			side by side &mdash; the cocked shooting leg, the outstretched arm, the AVR-sashed Palace shirt &mdash; with
			the verdict:
		</p>

		<blockquote class="k2-boxart__quote">
			<p>&ldquo;Yes! In fact, I&rsquo;d say it was drawn from this picture actually&hellip; but just imagined
			from a different angle.&rdquo;</p>
			<cite>&mdash; Steve B, closing the case</cite>
		</blockquote>

		<figure class="k2-boxart__figure">
			<img src="<?php echo $img('boxart-vs-photo-sidebyside.png'); ?>" alt="The Kick Off 2 box art beside the black-and-white press photo of Andy Gray, showing the same shooting pose from a different angle." loading="lazy" decoding="async" />
			<figcaption>Cover (left) vs. the press photo (right). Same player, same strike &mdash; Buxton just spun the camera around.</figcaption>
		</figure>
	</section>

	<section class="k2-boxart__section">
		<h2 class="k2-boxart__h2">Confirmation from the source</h2>
		<p class="k2-boxart__prose">
			And then, the clincher. Our member <strong>Jorn</strong> went straight to the top and asked
			<strong>Steve Screech</strong> &mdash; co-creator of Kick Off 2 alongside Dino Dini &mdash; about the cover.
			Screech replied without hesitation: it&rsquo;s <strong>Andy Gray, Crystal Palace</strong>.
		</p>
		<p class="k2-boxart__prose">
			Here&rsquo;s the kicker: Steve Screech is a lifelong Crystal Palace fan. So when an Eagle ended up
			immortalised on the cover of the greatest football game ever made&hellip; let&rsquo;s just say we
			don&rsquo;t think that was entirely an accident. 😏
		</p>
		<p class="k2-boxart__prose">
			Beautifully, the 2006 forum thread had quietly nailed it all along. Way back then, a poster called
			Steve1977 mused:
		</p>
		<blockquote class="k2-boxart__quote">
			<p>&ldquo;I reckon the player standing up about to take the shot is a former Crystal Palace
			player&hellip; Andy Gray (not the Sky commentator) possibly? The coach looks like former Birmingham City
			manager Terry Cooper. Incidentally, he looks a little too &lsquo;excited&rsquo; lol.&rdquo;</p>
			<cite>&mdash; Steve1977, KOA forum, 2006</cite>
		</blockquote>
		<p class="k2-boxart__prose">
			Andy Gray in front, Hugo Sánchez behind, and a coach who is possibly Terry Cooper and is
			<em>definitely</em> having the time of his life. It took twenty years and a frantic day of group-chat
			sleuthing, but the cover finally gave up its secrets.
		</p>
	</section>

	<section class="k2-boxart__section">
		<h2 class="k2-boxart__h2">It even greeted us at boot</h2>
		<p class="k2-boxart__prose">
			And here&rsquo;s the thing &mdash; this painting wasn&rsquo;t just on the box. The very same image loaded up
			on screen every time you started the game. Andy Gray and Hugo Sánchez were the first thing thousands of
			us saw as the floppy chattered away and the Amiga whirred to life.
		</p>
		<figure class="k2-boxart__figure k2-boxart__figure--screen">
			<img src="<?php echo $img('ko2-loading-screen.png'); ?>" alt="The Kick Off 2 Amiga loading screen, showing the same cover illustration in lower-resolution game form." loading="lazy" decoding="async" />
			<figcaption>The Kick Off 2 loading screen &mdash; the cover art, rendered in glorious 1990 Amiga colours.</figcaption>
		</figure>
	</section>

	<section class="k2-boxart__section">
		<h2 class="k2-boxart__h2">After the dust settled</h2>
		<p class="k2-boxart__prose">
			6 June answered the <em>who</em>. In the weeks that followed, two quieter beats reminded us this story
			is bigger than a box &mdash; it&rsquo;s people, fans, and a community that looks after its treasures.
		</p>
		<p class="k2-boxart__prose">
			First: the photograph of Stainy&rsquo;s original had genuinely slipped out of public view. Stainy lives
			in the US now; the drawing is still on his wall, but the picture the forum once had was gone &mdash; out of
			sight until someone went looking. <strong>Alkis</strong> reached out across the Atlantic, Stainy replied
			straight away, and Alkis restored the artwork photograph to the
			<a href="https://ko-gathering.com/forum/viewtopic.php?t=12246" rel="noopener noreferrer">forum thread</a>
			&mdash; preserved for everyone again before it could stay lost. As Steve B put it: &ldquo;Ok Alkis messaged
			Stainy, and Stainy replied quickly.. and Alkis restored the art work picture on the forum!&rdquo; A small
			act of care, and suddenly a piece of KO2 history was back where it belongs.
		</p>
		<p class="k2-boxart__prose">
			Second: Steve B hadn&rsquo;t finished with that press photo. He wrote to the eBay seller &mdash; a lifelong
			Crystal Palace collector called <strong>John</strong> &mdash; to ask where the picture had come from, and
			floated the same theory the group had landed on: might this &ldquo;Ian Wright&rdquo; label be wrong, and
			might this very shot have inspired the cover?
		</p>
		<figure class="k2-boxart__figure k2-boxart__figure--chat">
			<img src="<?php echo $img('photo_owner.jpg'); ?>" alt="eBay messages between Steve B and seller John about the Crystal Palace press photo, including John&#39;s family history at the club." loading="lazy" decoding="async" />
			<figcaption>
				John&rsquo;s reply is a little treasure in its own right: lifelong Palace, father who played for the club
				in the late fifties, grandfather who worked on the Arthur Waite stand, and a collection built from programme
				fairs plus a bulk buy of some six thousand football press photos &mdash; about two hundred of them Palace.
				He&rsquo;d wondered about Andy Gray too; Google kept insisting Ian Wright.
			</figcaption>
		</figure>
		<p class="k2-boxart__prose">
			That&rsquo;s the thing about this cover &mdash; it keeps opening doors. A mislabelled press photo, a forum
			thread from 2006, a drawing on a wall in America. None of it was ever just box art.
		</p>
		<p class="k2-boxart__prose">
			The player hunt &mdash; the mislabelled press photo, the WhatsApp theories, the Reddit cavalry, the word
			from a Kick Off 2 co-creator &mdash; came together in a single glorious flurry on <strong>6 June
			2026</strong>. Not bad for a 35-year-old pencil drawing.
		</p>
	</section>

	<footer class="k2-boxart__outro">
		<p class="k2-boxart__credits">
			Sleuthing by the KO2 Online WhatsApp crew &mdash; Steve B, Mike C, Jorn, Steve C &amp; co. With thanks to
			r/crystalpalace, the <a href="https://ko-gathering.com/forum/viewtopic.php?t=12246" rel="noopener noreferrer">KOA forum</a>,
			Cameron Buxton, Stainy, Alkis, and Steve Screech.
		</p>
		<p class="k2-boxart__back"><a class="k2-link-star" href="/status.php">&larr; Back to the status room</a></p>
	</footer>

</article>
