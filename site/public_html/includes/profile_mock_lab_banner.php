<?php
/**
 * Lab chrome for profile mocks. Expects $pmMockVariant, $pmMockTitle, $pmMockThesis (strings).
 */
$vid = pm_h($pmMockVariant);
$portalId = (int) $pm['id'];
?>
<div class="pm-lab-banner" role="note">
	<div class="pm-lab-banner__main">
		<span class="pm-lab-banner__tag">Profile lab</span>
		<h1 class="pm-lab-banner__title">Mock <?php echo $vid; ?> — <?php echo pm_h($pmMockTitle); ?></h1>
		<p class="pm-lab-banner__thesis"><?php echo pm_h($pmMockThesis); ?></p>
	</div>
	<nav class="pm-lab-banner__nav" aria-label="Profile mock navigation">
		<a href="profile_mocks.php?id=<?php echo $portalId; ?>">All mocks</a>
		<a href="individual1.php?id=<?php echo $portalId; ?>">Production profile</a>
		<a href="profile_mock_a.php?id=<?php echo $portalId; ?>"<?php echo $pmMockVariant === 'A' ? ' class="is-current"' : ''; ?>>A</a>
		<a href="profile_mock_b.php?id=<?php echo $portalId; ?>"<?php echo $pmMockVariant === 'B' ? ' class="is-current"' : ''; ?>>B</a>
		<a href="profile_mock_c.php?id=<?php echo $portalId; ?>"<?php echo $pmMockVariant === 'C' ? ' class="is-current"' : ''; ?>>C</a>
	</nav>
</div>
