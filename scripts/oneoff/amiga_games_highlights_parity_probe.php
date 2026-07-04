<?php
declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_hub_lib.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_games_lib.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_highlights_helpers.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';
function rows(mysqli $c, string $sql, string $types='', array $params=[]): array {
  $s=$c->prepare($sql); if(!$s) throw new RuntimeException($c->error);
  if($types!=='') $s->bind_param($types,...$params);
  $s->execute(); $r=$s->get_result(); $out=[]; while($row=$r->fetch_assoc()) $out[]=$row; if($r)$r->free(); $s->close(); return $out;
}
function old_fetch(mysqli $con, string $board, AmigaSnapshotContext $ctx, int $limit=100, string $scope='all'): array {
  $types=''; $params=[]; $cutoffSql=amiga_snapshot_rated_game_cutoff_and_sql($ctx,$types,$params);
  $where='1=1'.$cutoffSql; if($scope==='world-cup') $where.=' AND '.amiga_games_world_cup_name_sql('r.tournament_name');
  $select=amiga_realm_games_hub_select_sql().amiga_rated_games_from_sql().' WHERE '.$where;
  switch($board){
    case 'biggest_draws': $sql=$select.' AND ABS(r.ActualScore - 0.5) < 0.001 ORDER BY r.SumOfGoals DESC, r.id ASC LIMIT '.$limit; break;
    case 'top_score': $sql=$select.' ORDER BY GREATEST(r.GoalsA, r.GoalsB) DESC, r.SumOfGoals DESC, r.id ASC LIMIT '.$limit; break;
    case 'biggest_wins': $sql=$select.' AND ABS(r.ActualScore - 0.5) >= 0.001 ORDER BY r.GoalDifference DESC, r.id ASC LIMIT '.$limit; break;
    case 'biggest_upsets': $sql=$select.amiga_games_highlights_underdog_win_sql().' ORDER BY '.amiga_games_highlights_winner_adjustment_sql().' DESC, r.id ASC LIMIT '.$limit; break;
    default: $sql=$select.' ORDER BY r.SumOfGoals DESC, r.id ASC LIMIT '.$limit;
  }
  return rows($con,$sql,$types,$params);
}
$con = new mysqli($dbhost,$username,$password,$database,$dbportnum);
$boards=['most_goals','biggest_wins','biggest_draws','top_score','biggest_upsets'];
foreach (['present','year:2024','month:2014-07'] as $as) {
  if($as==='present'){amiga_snapshot_context_reset();$ctx=AmigaSnapshotContext::present();}
  else{$_GET['as']=$as;amiga_snapshot_context_reset();$ctx=amiga_snapshot_context_from_request($con);}
  echo "=== $as ===\n";
  foreach($boards as $board){
    $old=old_fetch($con,$board,$ctx);
    $new=amiga_games_highlights_fetch($con,$board,$ctx);
    echo '  '.$board.': '.(json_encode($old)===json_encode($new)?'OK':'DIFF')."\n";
  }
}
$con->close();