<?php
include 'includes/header.php';
require_once "includes/db.php";

/* -----------------------------
   Heatmap
----------------------------- */

function heatClass($value,$min,$max){

    if($max == $min) return "heat-mid";

    $ratio = ($value-$min)/($max-$min);

    if($ratio > .66) return "heat-high";
    if($ratio < .33) return "heat-low";

    return "heat-mid";
}

/* -----------------------------
   Matrix Selection
----------------------------- */

$matrix = $_GET['matrix'] ?? 'creatures';

/* -----------------------------
   SQL Definitions
----------------------------- */

if($matrix=="creatures"){

$sql="
    SELECT
    c.name,
    c.type,
    c.level,
    c.strength,
    c.health,

    ROUND(c.strength+(c.strength*c.strength_bonus/100)) total_strength,
    ROUND(c.health+(c.health*c.health_bonus/100)) total_health,

    ROUND((c.strength+(c.strength*c.strength_bonus/100))+
    (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Mel' THEN cb.bonus_percent END),0)/100)) attack_vs_mel,

    ROUND((c.strength+(c.strength*c.strength_bonus/100))+
    (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Rng' THEN cb.bonus_percent END),0)/100)) attack_vs_rng,

    ROUND((c.strength+(c.strength*c.strength_bonus/100))+
    (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Mtd' THEN cb.bonus_percent END),0)/100)) attack_vs_mtd,

    ROUND((c.strength+(c.strength*c.strength_bonus/100))+
    (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Fly' THEN cb.bonus_percent END),0)/100)) attack_vs_fly

    FROM creature c
    LEFT JOIN creature_bonus cb ON cb.creatureID=c.creatureID
    GROUP BY c.creatureID
    ORDER BY c.level,c.name
";
}

elseif($matrix=="fighters"){

$sql="
    SELECT
    f.name,
    f.type,
    f.level,
    f.strength,
    f.health,

    ROUND(f.strength+(f.strength*f.strength_bonus/100)) total_strength,
    ROUND(f.health+(f.health*f.health_bonus/100)) total_health,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Mel' THEN fb.bonus_percent END),0)/100)) attack_vs_mel,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Rng' THEN fb.bonus_percent END),0)/100)) attack_vs_rng,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Mtd' THEN fb.bonus_percent END),0)/100)) attack_vs_mtd,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Fly' THEN fb.bonus_percent END),0)/100)) attack_vs_fly

    FROM fighter f

    LEFT JOIN fighter_bonus fb ON fb.fighterID=f.fighterID

    WHERE unit = 'Reg'

    GROUP BY f.fighterID
    ORDER BY f.level,f.name
";
}

elseif($matrix=="specialists"){

$sql="
    SELECT
    f.name,
    f.type,
    f.level,
    f.strength,
    f.health,

    ROUND(f.strength+(f.strength*f.strength_bonus/100)) total_strength,
    ROUND(f.health+(f.health*f.health_bonus/100)) total_health,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Mel' THEN fb.bonus_percent END),0)/100)) attack_vs_mel,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Rng' THEN fb.bonus_percent END),0)/100)) attack_vs_rng,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Mtd' THEN fb.bonus_percent END),0)/100)) attack_vs_mtd,

    ROUND((f.strength+(f.strength*f.strength_bonus/100))+
    (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Fly' THEN fb.bonus_percent END),0)/100)) attack_vs_fly

    FROM fighter f

    LEFT JOIN fighter_bonus fb ON fb.fighterID=f.fighterID
    
    WHERE unit = 'Spc'

    GROUP BY f.fighterID
    ORDER BY f.level,f.name
";
}

elseif($matrix=="mercs"){

$sql="
    SELECT
    m.name,
    m.type,
    m.level,
    m.strength,
    m.health,

    ROUND(m.strength+(m.strength*m.strength_bonus/100)) total_strength,
    ROUND(m.health+(m.health*m.health_bonus/100)) total_health,

    ROUND((m.strength+(m.strength*m.strength_bonus/100))+
    (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Mel' THEN mb.bonus_percent END),0)/100)) attack_vs_mel,

    ROUND((m.strength+(m.strength*m.strength_bonus/100))+
    (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Rng' THEN mb.bonus_percent END),0)/100)) attack_vs_rng,

    ROUND((m.strength+(m.strength*m.strength_bonus/100))+
    (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Mtd' THEN mb.bonus_percent END),0)/100)) attack_vs_mtd,

    ROUND((m.strength+(m.strength*m.strength_bonus/100))+
    (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Fly' THEN mb.bonus_percent END),0)/100)) attack_vs_fly

    FROM merc m
    LEFT JOIN merc_bonus mb ON mb.mercID=m.mercID
    GROUP BY m.mercID
    ORDER BY m.level,m.name
";
}

elseif($matrix=="monsters"){

$sql="
    SELECT
    name,
    type,
    level,
    strength,
    health,

    strength AS total_strength,
    health AS total_health,

    strength AS attack_vs_mel,
    strength AS attack_vs_rng,
    strength AS attack_vs_mtd,
    strength AS attack_vs_fly

    FROM monster

    ORDER BY level,name
";
}

else{


}

$stmt=$pdo->query($sql);
$data=$stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>

<title>Creature Combat Data</title>
    <style>

        /* sticky title */

        .page-title{
            /*position:sticky;*/
            top:0;
            background:#333;
            color:#fff;
            padding:12px;
            font-size:20px;
            text-align:center;
            z-index:10;
        }

        /* table */

        table{
            margin:20px auto;
            border-collapse:collapse;
            border:1px solid #888;
        }

        /* sticky header */

        thead th{
            /*position:sticky;*/
            top:48px;
            background:#f5f5f5;
        }

        /* cells */

        th,td{
            padding:6px;
            border:1px solid #ccc;
            text-align:center;
        }

        /* column widths */

        .col-name{
            width:550px;
            text-align:left;
        }

        .col{
            width:95px;
        }

        /* heatmap colors */

        .heat-low{
            background:#f8c8c8;
        }

        .heat-mid{
            background:#fff3a6;
        }

        .heat-high{
            background:#b6f0b6;
        }

        .stat{
            text-align:right;
            width:95px;
        }

        .sort{
            cursor:pointer;
        user-select:none;
        }

        .sort::after{
        content:" ⇅";
            font-size:12px;
        color:#999;
        margin-left:4px;
        }

        .sort.asc::after{
        content:" ▲";
        color:#333;
        }

        .sort.desc::after{
        content:" ▼";
        color:#333;
        }

        /* top bar */

        .topbar{
        position:sticky;
        top:0;
        background:#333;
        color:#fff;

        display:flex;
        align-items:center;
        justify-content:center;
        gap:20px;

        padding:10px 0;
        z-index:10;
        }

        /* title */

        .title{
        font-size:20px;
        font-weight:bold;
        }

        /* selector form */

        .selector{
        display:flex;
        align-items:center;
        gap:10px;
        margin:0;
        }

        /* label */

        .selector label{
        font-weight:bold;
        }

        /* dropdown */

        .selector select{
        width:250px;
        padding:6px;
        }

        /* button */

        .selector button{
        padding:6px 12px;
        cursor:pointer;
        }
    </style>

</head>
 <body>

    <div class="topbar">

        <div class="title">
            <h2><?= ucfirst($matrix) ?> Combat Matrix</h2>
        </div>

        <form method="get" class="selector">
            <label></label>
            <select name="matrix">
            <option value="creatures" <?=($matrix=='creatures')?'selected':''?>>Creatures</option>
            <option value="monsters" <?=($matrix=='monsters')?'selected':''?>>Monsters</option>
            <option value="fighters" <?=($matrix=='fighters')?'selected':''?>>Fighters</option>
            <option value="mercs" <?=($matrix=='mercs')?'selected':''?>>Mercs</option>

            </select>
                <button type="submit">Load</button>
            </form>
    </div>

    <table id="matrix">
        <thead class="matrix">
            <tr>
                <th class="name sort" data-col="0">Name</th>
                <th class="sort" data-col="1">Type</th>
                <th class="sort" data-col="2">Lvl</th>
                <th class="sort" data-col="3">Str</th>
                <th class="sort" data-col="4">Hth</th>
                <th class="sort" data-col="5">TStr</th>
                <th class="sort" data-col="6">THth</th>
                <th class="sort" data-col="7">vs Mel</th>
                <th class="sort" data-col="8">vs Rng</th>
                <th class="sort" data-col="9">vs Mtd</th>
                <th class="sort" data-col="10">vs Fly</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach($data as $r):

            $att=[
            $r['attack_vs_mel'],
            $r['attack_vs_rng'],
            $r['attack_vs_mtd'],
            $r['attack_vs_fly']
            ];

            $max=max($att);
            $min=min($att);

            ?>

            <tr>

                <td class="name col-name"><?=htmlspecialchars($r['name'])?></td>
                <td><?=$r['type']?></td>
                <td><?=$r['level']?></td>

                <td class="stat"><?=number_format((float)$r['strength'], 0)?></td>
                <td class="stat"><?=number_format((float)$r['health'], 0)?></td>

                <td class="stat"><?=number_format((float)$r['total_strength'], 0)?></td>
                <td class="stat"><?=number_format((float)$r['total_health'], 0)?></td>

                <td class="stat <?=heatClass($r['attack_vs_mel'],$min,$max)?>"><?= number_format((float)$r['attack_vs_mel'], 0) ?></td>
                <td class="stat <?=heatClass($r['attack_vs_rng'],$min,$max)?>"><?=number_format((float)$r['attack_vs_rng'], 0)?></td>
                <td class="stat <?=heatClass($r['attack_vs_mtd'],$min,$max)?>"><?=number_format((float)$r['attack_vs_mtd'], 0)?></td>
                <td class="stat <?=heatClass($r['attack_vs_fly'],$min,$max)?>"><?=number_format((float)$r['attack_vs_fly'], 0)?></td>

            </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

        <script>

            /* table sorting */

            document.querySelectorAll(".sort").forEach((header)=>{

            let asc=true;

            header.addEventListener("click",()=>{

            const table=document.getElementById("matrix");
            const tbody=table.querySelector("tbody");
            const rows=[...tbody.querySelectorAll("tr")];
            const col=header.dataset.col;

            rows.sort((a,b)=>{

            let A=a.children[col].innerText.replace(/,/g,'');
            let B=b.children[col].innerText.replace(/,/g,'');

            let nA=parseFloat(A);
            let nB=parseFloat(B);

            if(!isNaN(nA)&&!isNaN(nB)){
            return asc?nA-nB:nB-nA;
            }

            return asc?A.localeCompare(B):B.localeCompare(A);

            });

            document.querySelectorAll(".sort").forEach(h=>{
            h.classList.remove("asc","desc");
            });

            header.classList.toggle("asc",asc);
            header.classList.toggle("desc",!asc);

            asc=!asc;

            rows.forEach(tr=>tbody.appendChild(tr));

            });

            });

        </script>

    </body>
</html>
