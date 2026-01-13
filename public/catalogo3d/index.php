<?php
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);

require __DIR__.DIRECTORY_SEPARATOR.'config.php';

function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo=new PDO('mysql:host='.$GLOBALS['DB_HOST'].';charset=utf8mb4',$GLOBALS['DB_USER'],$GLOBALS['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$GLOBALS['DB_NAME'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo=new PDO('mysql:host='.$GLOBALS['DB_HOST'].';dbname='.$GLOBALS['DB_NAME'].';charset=utf8mb4',$GLOBALS['DB_USER'],$GLOBALS['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS files (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255), original_name VARCHAR(255), ext VARCHAR(10), path VARCHAR(255), thumb_path VARCHAR(255), size BIGINT, description TEXT, print_time INT, material_amount DECIMAL(10,2), multicolor TINYINT(1), category_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(category_id), FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL)');
    return $pdo;
}
function ensureDirs(): void {
    foreach (['uploads','thumbnails'] as $d) {
        $p=__DIR__.DIRECTORY_SEPARATOR.$d;
        if (!is_dir($p)) mkdir($p,0775,true);
    }
}
function categories(): array {
    return db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}
function categoryIdOrCreate(?string $name, ?int $selectedId): ?int {
    if ($selectedId) return $selectedId;
    $n=trim((string)$name);
    if ($n==='') return null;
    $pdo=db();
    $stmt=$pdo->prepare('SELECT id FROM categories WHERE name=:n');
    $stmt->execute([':n'=>$n]);
    $id=$stmt->fetchColumn();
    if ($id) return (int)$id;
    $stmt=$pdo->prepare('INSERT INTO categories(name) VALUES(:n)');
    $stmt->execute([':n'=>$n]);
    return (int)$pdo->lastInsertId();
}
function allowedExt(string $ext): bool {
    return in_array(strtolower($ext),['stl','3mf'],true);
}
function saveThumb(string $dataUrl, string $basename): ?string {
    if (!$dataUrl || strpos($dataUrl,'data:image/png;base64,')!==0) return null;
    $raw=base64_decode(substr($dataUrl,22));
    $path='thumbnails/'.$basename.'.png';
    file_put_contents(__DIR__.DIRECTORY_SEPARATOR.$path,$raw);
    return $path;
}
function formatSize(int $bytes): string {
    $u=['B','KB','MB','GB','TB'];
    $i=0;
    $v=(float)$bytes;
    while ($v>=1024 && $i<count($u)-1){$v=$v/1024;$i++;}
    return number_format($v, ($i===0?0:2), ',','.') .' '.$u[$i];
}
function post($k,$d=null){return isset($_POST[$k])?$_POST[$k]:$d;}
function get($k,$d=null){return isset($_GET[$k])?$_GET[$k]:$d;}

ensureDirs();

if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='upload') {
    $file=$_FILES['file']??null;
    if (!$file || $file['error']!==UPLOAD_ERR_OK) {header('Location: ?page=upload&error=arquivo');exit;}
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if (!allowedExt($ext)) {header('Location: ?page=upload&error=ext');exit;}
    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=$finfo->file($file['tmp_name']);
    $allowed=['model/stl','application/3mf','application/octet-stream','application/vnd.ms-package.3dmanufacturing'];
    if (!in_array($mime,$allowed,true)) {header('Location: ?page=upload&error=mime');exit;}
    $basename=bin2hex(random_bytes(8)).'_'.time();
    $target='uploads/'.$basename.'.'.$ext;
    move_uploaded_file($file['tmp_name'],__DIR__.DIRECTORY_SEPARATOR.$target);
    $thumb=saveThumb((string)post('thumb_data',''),$basename);
    $desc=trim((string)post('description',''));
    $ptime=(int)post('print_time',0);
    $mat=number_format((float)post('material_amount',0),2,'.','');
    $multi=post('multicolor')==='1'?1:0;
    $catSel=post('category_id')? (int)post('category_id'):null;
    $catNew=post('new_category')? (string)post('new_category'):null;
    $catId=categoryIdOrCreate($catNew,$catSel);
    $stmt=db()->prepare('INSERT INTO files(filename,original_name,ext,path,thumb_path,size,description,print_time,material_amount,multicolor,category_id) VALUES(:fn,:on,:ex,:pa,:th,:sz,:de,:pt,:ma,:mu,:ci)');
    $stmt->execute([
        ':fn'=>$basename.'.'.$ext,
        ':on'=>$file['name'],
        ':ex'=>$ext,
        ':pa'=>$target,
        ':th'=>$thumb,
        ':sz'=>filesize(__DIR__.DIRECTORY_SEPARATOR.$target),
        ':de'=>$desc,
        ':pt'=>$ptime,
        ':ma'=>$mat,
        ':mu'=>$multi,
        ':ci'=>$catId
    ]);
    header('Location: ?page=list&ok=1');exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='edit') {
    $id=(int)post('id');
    $desc=trim((string)post('description',''));
    $ptime=(int)post('print_time',0);
    $mat=number_format((float)post('material_amount',0),2,'.','');
    $multi=post('multicolor')==='1'?1:0;
    $catSel=post('category_id')? (int)post('category_id'):null;
    $catNew=post('new_category')? (string)post('new_category'):null;
    $catId=categoryIdOrCreate($catNew,$catSel);
    $stmt=db()->prepare('UPDATE files SET description=:de, print_time=:pt, material_amount=:ma, multicolor=:mu, category_id=:ci WHERE id=:id');
    $stmt->execute([':de'=>$desc,':pt'=>$ptime,':ma'=>$mat,':mu'=>$multi,':ci'=>$catId,':id'=>$id]);
    header('Location: ?page=list&ok=2');exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='delete') {
    $id=(int)post('id');
    $row=db()->query('SELECT path,thumb_path FROM files WHERE id='.(int)$id)->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        @unlink(__DIR__.DIRECTORY_SEPARATOR.$row['path']);
        if ($row['thumb_path']) @unlink(__DIR__.DIRECTORY_SEPARATOR.$row['thumb_path']);
        db()->exec('DELETE FROM files WHERE id='.(int)$id);
    }
    header('Location: ?page=list&ok=3');exit;
}

$page=get('page','dashboard');
$cats=categories();
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Catálogo 3D</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#f7f8fa;--card:#ffffff;--text:#1f2937;--muted:#6b7280;--primary:#2563eb;--border:#e5e7eb}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;padding:0;background:var(--bg);color:var(--text)}
.header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:var(--card);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10}
.nav a{margin-right:12px;padding:8px 12px;border-radius:8px;text-decoration:none;color:var(--text);border:1px solid var(--border)}
.nav a.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.container{max-width:1100px;margin:24px auto;padding:0 16px}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
.stat{padding:16px;border:1px solid var(--border);border-radius:10px;background:var(--card)}
.label{font-size:12px;color:var(--muted)}
.value{font-size:20px;font-weight:600}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid var(--border);padding:8px;text-align:left}
.button{background:var(--primary);color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
.input,select,textarea{width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:#fff}
.row{display:flex;gap:12px;align-items:center;margin-top:8px}
.thumb{width:64px;height:64px;border:1px solid var(--border);border-radius:8px;object-fit:cover;background:#eee}
.preview{width:320px;height:240px;border:1px solid var(--border);border-radius:8px;background:#fff;display:flex;align-items:center;justify-content:center;margin-top:8px}
.notice{padding:10px;border:1px solid var(--border);border-radius:8px;background:#eef2ff;margin-bottom:12px}
.badge{display:inline-block;padding:4px 8px;border:1px solid var(--border);border-radius:999px;font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="header">
  <div style="font-weight:600">Catálogo 3D</div>
  <div class="nav">
    <a href="?page=dashboard" class="<?php echo $page==='dashboard'?'active':'';?>">Dashboard</a>
    <a href="?page=upload" class="<?php echo $page==='upload'?'active':'';?>">Upload</a>
    <a href="?page=list" class="<?php echo $page==='list'?'active':'';?>">Arquivos</a>
  </div>
</div>
<div class="container">
<?php if ($page==='dashboard'): ?>
<?php
$tot=db()->query('SELECT COUNT(*) c, COALESCE(SUM(size),0) s, COALESCE(SUM(print_time),0) pt, COALESCE(SUM(material_amount),0) ma FROM files')->fetch(PDO::FETCH_ASSOC);
$top=db()->query('SELECT c.name, COUNT(f.id) qtd FROM categories c LEFT JOIN files f ON f.category_id=c.id GROUP BY c.id ORDER BY qtd DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$recent=db()->query('SELECT id,original_name,thumb_path,created_at FROM files ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="grid">
  <div class="stat" style="grid-column:span 3"><div class="label">Total de arquivos</div><div class="value"><?php echo (int)$tot['c'];?></div></div>
  <div class="stat" style="grid-column:span 3"><div class="label">Tamanho total</div><div class="value"><?php echo formatSize((int)$tot['s']);?></div></div>
  <div class="stat" style="grid-column:span 3"><div class="label">Tempo total (min)</div><div class="value"><?php echo (int)$tot['pt'];?></div></div>
  <div class="stat" style="grid-column:span 3"><div class="label">Material total (g)</div><div class="value"><?php echo number_format((float)$tot['ma'],2,',','.');?></div></div>
</div>
<div class="card">
  <div style="font-weight:600;margin-bottom:8px">Top categorias</div>
  <?php if (!$top): ?><div class="badge">Sem dados</div><?php else: foreach($top as $t): ?>
  <div class="row"><div><?php echo htmlspecialchars($t['name']);?></div><div class="badge"><?php echo (int)$t['qtd'];?> arquivos</div></div>
  <?php endforeach; endif; ?>
</div>
<div class="card">
  <div style="font-weight:600;margin-bottom:8px">Últimos envios</div>
  <div class="grid">
    <?php foreach($recent as $r): ?>
    <div class="stat" style="grid-column:span 3">
      <img src="<?php echo $r['thumb_path']? htmlspecialchars($r['thumb_path']):'';?>" class="thumb" alt="">
      <div style="margin-top:6px"><?php echo htmlspecialchars($r['original_name']);?></div>
      <div class="label"><?php echo htmlspecialchars($r['created_at']);?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php elseif ($page==='upload'): ?>
<?php if (get('error')): ?><div class="notice">Falha no upload: <?php echo htmlspecialchars((string)get('error'));?></div><?php endif; ?>
<div class="card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload">
    <div>Arquivo (.stl ou .3mf)</div>
    <input class="input" type="file" name="file" id="file" accept=".stl,.3mf" required>
    <div class="preview" id="preview">Pré-visualização</div>
    <input type="hidden" name="thumb_data" id="thumb_data">
    <div class="row">
      <div style="flex:1">
        <div>Categoria</div>
        <select name="category_id" class="input">
          <option value="">Selecionar...</option>
          <?php foreach($cats as $c): ?>
          <option value="<?php echo (int)$c['id'];?>"><?php echo htmlspecialchars($c['name']);?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1">
        <div>Nova categoria</div>
        <input type="text" name="new_category" class="input" placeholder="Ex.: Brinquedos">
      </div>
    </div>
    <div>Descrição</div>
    <textarea name="description" class="input" rows="3"></textarea>
    <div class="row">
      <div style="flex:1">
        <div>Tempo de impressão (min)</div>
        <input type="number" min="0" name="print_time" class="input" value="0">
      </div>
      <div style="flex:1">
        <div>Material (g)</div>
        <input type="number" step="0.01" min="0" name="material_amount" class="input" value="0">
      </div>
      <div style="flex:1">
        <div>Multicor</div>
        <select name="multicolor" class="input">
          <option value="0">Não</option>
          <option value="1">Sim</option>
        </select>
      </div>
    </div>
    <div class="row" style="justify-content:flex-end;margin-top:16px">
      <button class="button" type="submit">Enviar</button>
    </div>
  </form>
</div>
<script type="module">
import * as THREE from 'https://unpkg.com/three@0.158.0/build/three.module.js';
import { STLLoader } from 'https://unpkg.com/three@0.158.0/examples/jsm/loaders/STLLoader.js';
import { ThreeMFLoader } from 'https://unpkg.com/three@0.158.0/examples/jsm/loaders/3MFLoader.js';

const input=document.getElementById('file');
const preview=document.getElementById('preview');
const hidden=document.getElementById('thumb_data');

function renderObject(obj){
  preview.innerHTML='';
  const w=320,h=240;
  const scene=new THREE.Scene();
  const camera=new THREE.PerspectiveCamera(45,w/h,0.1,1000);
  const renderer=new THREE.WebGLRenderer({antialias:true,preserveDrawingBuffer:true});
  renderer.setSize(w,h);
  preview.appendChild(renderer.domElement);
  const light1=new THREE.DirectionalLight(0xffffff,1);light1.position.set(1,1,1);scene.add(light1);
  const light2=new THREE.AmbientLight(0xffffff,0.5);scene.add(light2);
  let mesh=obj;
  if (obj.isBufferGeometry){
    mesh=new THREE.Mesh(obj,new THREE.MeshPhongMaterial({color:0x6699cc,flatShading:true}));
  }
  scene.add(mesh);
  const box=new THREE.Box3().setFromObject(mesh);
  const size=box.getSize(new THREE.Vector3());
  const center=box.getCenter(new THREE.Vector3());
  mesh.position.sub(center);
  const maxDim=Math.max(size.x,size.y,size.z);
  const scale=2/maxDim;
  mesh.scale.setScalar(scale);
  camera.position.set(2.5,2,2.5);
  camera.lookAt(0,0,0);
  renderer.render(scene,camera);
  hidden.value=renderer.domElement.toDataURL('image/png');
}

input.addEventListener('change',async e=>{
  const file=input.files[0];
  if(!file){return;}
  const ext=file.name.split('.').pop().toLowerCase();
  const buf=await file.arrayBuffer();
  if(ext==='stl'){
    const loader=new STLLoader();
    const geom=loader.parse(buf);
    renderObject(geom);
  }else if(ext==='3mf'){
    const loader=new ThreeMFLoader();
    const group=loader.parse(buf);
    renderObject(group);
  }else{
    preview.textContent='Extensão não suportada';
    hidden.value='';
  }
});
</script>
<?php elseif ($page==='list'): ?>
<div class="card">
  <form method="get" class="row">
    <input type="hidden" name="page" value="list">
    <div style="flex:1">
      <div>Categoria</div>
      <select name="cat" class="input" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach($cats as $c): ?>
        <option value="<?php echo (int)$c['id'];?>" <?php echo get('cat')==(string)$c['id']?'selected':'';?>><?php echo htmlspecialchars($c['name']);?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
  <?php
  $where='';
  if (get('cat')) $where='WHERE category_id='.(int)get('cat');
  $rows=db()->query('SELECT f.id,f.original_name,f.thumb_path,f.size,f.description,f.print_time,f.material_amount,f.multicolor,c.name cat FROM files f LEFT JOIN categories c ON c.id=f.category_id '.$where.' ORDER BY f.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <table class="table">
    <thead><tr><th>Thumb</th><th>Nome</th><th>Categoria</th><th>Tamanho</th><th>Tempo</th><th>Material</th><th>Multicor</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><img src="<?php echo $r['thumb_path']? htmlspecialchars($r['thumb_path']):'';?>" class="thumb" alt=""></td>
        <td><?php echo htmlspecialchars($r['original_name']);?></td>
        <td><?php echo htmlspecialchars($r['cat']??'');?></td>
        <td><?php echo formatSize((int)$r['size']);?></td>
        <td><?php echo (int)$r['print_time'];?> min</td>
        <td><?php echo number_format((float)$r['material_amount'],2,',','.');?> g</td>
        <td><?php echo $r['multicolor']?'Sim':'Não';?></td>
        <td class="row" style="gap:6px">
          <a class="button" href="?page=edit&id=<?php echo (int)$r['id'];?>">Editar</a>
          <form method="post" onsubmit="return confirm('Excluir este arquivo?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int)$r['id'];?>">
            <button class="button" style="background:#ef4444">Excluir</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php elseif ($page==='edit'): ?>
<?php
$id=(int)get('id',0);
$it=db()->query('SELECT * FROM files WHERE id='.(int)$id)->fetch(PDO::FETCH_ASSOC);
?>
<div class="card">
  <form method="post">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" value="<?php echo (int)$id;?>">
    <div class="row">
      <img src="<?php echo $it['thumb_path']? htmlspecialchars($it['thumb_path']):'';?>" class="thumb" alt="">
      <div class="badge"><?php echo htmlspecialchars($it['original_name']);?></div>
    </div>
    <div class="row">
      <div style="flex:1">
        <div>Categoria</div>
        <select name="category_id" class="input">
          <option value="">Selecionar...</option>
          <?php foreach($cats as $c): ?>
          <option value="<?php echo (int)$c['id'];?>" <?php echo $it['category_id']==$c['id']?'selected':'';?>><?php echo htmlspecialchars($c['name']);?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1">
        <div>Nova categoria</div>
        <input type="text" name="new_category" class="input" placeholder="Ex.: Suportes">
      </div>
    </div>
    <div>Descrição</div>
    <textarea name="description" class="input" rows="3"><?php echo htmlspecialchars($it['description']??'');?></textarea>
    <div class="row">
      <div style="flex:1">
        <div>Tempo de impressão (min)</div>
        <input type="number" min="0" name="print_time" class="input" value="<?php echo (int)$it['print_time'];?>">
      </div>
      <div style="flex:1">
        <div>Material (g)</div>
        <input type="number" step="0.01" min="0" name="material_amount" class="input" value="<?php echo htmlspecialchars((string)$it['material_amount']);?>">
      </div>
      <div style="flex:1">
        <div>Multicor</div>
        <select name="multicolor" class="input">
          <option value="0" <?php echo $it['multicolor']?'':'selected';?>>Não</option>
          <option value="1" <?php echo $it['multicolor']?'selected':'';?>>Sim</option>
        </select>
      </div>
    </div>
    <div class="row" style="justify-content:flex-end;margin-top:16px">
      <button class="button" type="submit">Salvar</button>
    </div>
  </form>
</div>
<?php endif; ?>
</div>
</body>
</html>
