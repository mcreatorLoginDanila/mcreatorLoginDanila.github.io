<?php
// --- НАСТРОЙКА И АВТО-СОЗДАНИЕ ПАПОК ---
session_start();
define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('PROJECTS_DIR', DATA_DIR . '/projects');

// Если папок нет, скрипт создаст их сам
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
if (!is_dir(PROJECTS_DIR)) mkdir(PROJECTS_DIR, 0777, true);
if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, json_encode([]));

// --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ---
function getUsers() { return json_decode(file_get_contents(USERS_FILE), true); }
function saveUsers($users) { file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT)); }
function getProjectMeta($id) { $meta_path = PROJECTS_DIR . "/$id/meta.json"; return file_exists($meta_path) ? json_decode(file_get_contents($meta_path), true) : null; }
function saveProjectMeta($id, $meta) { file_put_contents(PROJECTS_DIR . "/$id/meta.json", json_encode($meta, JSON_PRETTY_PRINT)); }
// НОВЫЕ ФУНКЦИИ ДЛЯ КОММЕНТАРИЕВ
function getProjectComments($id) { $comments_path = PROJECTS_DIR . "/$id/comments.json"; return file_exists($comments_path) ? json_decode(file_get_contents($comments_path), true) : []; }
function saveProjectComments($id, $comments) { file_put_contents(PROJECTS_DIR . "/$id/comments.json", json_encode($comments, JSON_PRETTY_PRINT)); }


// --- ЛОГИКА (РОУТИНГ) ---
$page = $_GET['page'] ?? 'home';

// Регистрация, Вход, Выход
if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') { /* ... код без изменений ... */ }
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') { /* ... код без изменений ... */ }
if ($page === 'logout') { /* ... код без изменений ... */ }
// Полный код для этих блоков в самом конце, если нужно будет скопировать целиком

// Создание проекта (ИЗМЕНЕНО: папка builds и массив versions)
if ($page === 'create_project' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['username'])) {
    $project_id = time() . rand(100, 999);
    $project_path = PROJECTS_DIR . '/' . $project_id;
    mkdir($project_path);
    mkdir($project_path . '/code');
    mkdir($project_path . '/builds'); // Папка для версий
    $meta = [
        'id' => $project_id,
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'author' => $_SESSION['username'],
        'versions' => [] // Массив для хранения версий
    ];
    saveProjectMeta($project_id, $meta);
    header("Location: index.php?page=project&id=$project_id");
    exit;
}

// Загрузка файлов КОДА (без изменений)
if ($page === 'upload_code' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['username'])) {
    $project_id = $_POST['project_id'];
    $meta = getProjectMeta($project_id);
    if ($meta && $meta['author'] === $_SESSION['username'] && isset($_FILES['code_files'])) {
        $files = $_FILES['code_files'];
        $code_path = PROJECTS_DIR . "/$project_id/code/";
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                move_uploaded_file($files['tmp_name'][$i], $code_path . basename($files['name'][$i]));
            }
        }
    }
    header("Location: index.php?page=project&id=$project_id");
    exit;
}

// НОВОЕ: Публикация ВЕРСИИ проекта (заменяет upload_build)
if ($page === 'publish_version' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['username'])) {
    $project_id = $_POST['project_id'];
    $meta = getProjectMeta($project_id);
    if ($meta && $meta['author'] === $_SESSION['username'] && isset($_FILES['build_file']) && $_FILES['build_file']['error'] === UPLOAD_ERR_OK) {
        
        $version_name = preg_replace('/[^a-zA-Z0-9._-]/', '', $_POST['version_name']); // Очистка имени версии
        if(empty($version_name)) $version_name = 'v1.0';

        $version_path = PROJECTS_DIR . "/$project_id/builds/" . $version_name;
        if (!is_dir($version_path)) mkdir($version_path, 0777, true);

        $filename = basename($_FILES['build_file']['name']);
        move_uploaded_file($_FILES['build_file']['tmp_name'], $version_path . '/' . $filename);
        
        $new_version = [
            'name' => $_POST['version_name'],
            'notes' => $_POST['version_notes'],
            'file' => $filename,
            'timestamp' => time()
        ];

        $meta['versions'][] = $new_version;
        saveProjectMeta($project_id, $meta);
    }
    header("Location: index.php?page=project&id=$project_id");
    exit;
}

// НОВОЕ: Добавление комментария
if ($page === 'add_comment' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['username'])) {
    $project_id = $_POST['project_id'];
    $comment_text = trim($_POST['comment_text']);

    if ($project_id && !empty($comment_text)) {
        $comments = getProjectComments($project_id);
        $new_comment = [
            'author' => $_SESSION['username'],
            'text' => $comment_text,
            'timestamp' => time()
        ];
        $comments[] = $new_comment;
        saveProjectComments($project_id, $comments);
    }
    header("Location: index.php?page=project&id=$project_id#comments");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CodeFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f4f7f6; margin: 0; color: #333; }
        .container { max-width: 900px; margin: auto; padding: 20px; }
        .navbar { background: #fff; color: #333; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0;}
        .navbar a { color: #333; text-decoration: none; margin-left: 15px; }
        .navbar a.brand { font-weight: bold; font-size: 1.2em; }
        .card { background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        input, textarea, button { width: 100%; padding: 12px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; font-size: 1em; }
        button { background: #333; color: white; cursor: pointer; border: none; font-weight: bold; }
        button:hover { background: #555; }
        pre { background: #2d2d2d; color: #f1f1f1; padding: 1em; border-radius: 5px; white-space: pre-wrap; word-break: break-all; font-family: monospace; }
        .project-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .upload-section { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .download-button { display: inline-block; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }
        /* НОВЫЕ СТИЛИ */
        .version-item { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .version-item:last-child { border-bottom: none; margin-bottom: 0; }
        .version-meta { font-size: 0.9em; color: #666; }
        .comment { border-bottom: 1px solid #eee; padding: 15px 0; }
        .comment:last-child { border-bottom: none; }
        .comment-author { font-weight: bold; }
        .comment-date { font-size: 0.8em; color: #888; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="index.php" class="brand">CodeFolio</a>
        <div>
            <?php if(isset($_SESSION['username'])): ?>
                <span>Привет, <?= htmlspecialchars($_SESSION['username']) ?>!</span>
                <a href="index.php?page=create_project">Создать проект</a>
                <a href="index.php?page=logout">Выйти</a>
            <?php else: ?>
                <a href="index.php?page=login">Войти</a>
                <a href="index.php?page=register">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if(isset($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>

        <?php if($page === 'home'): ?>
            <div class="card">
                <h1>Все проекты</h1>
                <?php
                $projects = glob(PROJECTS_DIR . '/*');
                foreach (array_reverse($projects) as $project_path) {
                    $id = basename($project_path);
                    $meta = getProjectMeta($id);
                    if ($meta) {
                        echo "<div><a href='index.php?page=project&id={$meta['id']}'><strong>" . htmlspecialchars($meta['title']) . "</strong></a> от " . htmlspecialchars($meta['author']) . "</div><hr>";
                    }
                }
                ?>
            </div>
        <?php elseif($page === 'project'): ?>
            <?php 
            $id = $_GET['id'];
            $meta = getProjectMeta($id);
            if ($meta): ?>
                <div class="card">
                    <h1><?= htmlspecialchars($meta['title']) ?></h1>
                    <p>Автор: <?= htmlspecialchars($meta['author']) ?></p>
                    <p><?= nl2br(htmlspecialchars($meta['description'])) ?></p>
                </div>

                <div class="project-grid">
                    <div class="card">
                        <h3><span style="color: #007bff;">Исходный код</span></h3>
                        <?php
                        $code_files = glob(PROJECTS_DIR . "/$id/code/*");
                        foreach ($code_files as $file) {
                            echo "<a href='index.php?page=project&id=$id&view=" . urlencode(basename($file)) . "'>" . htmlspecialchars(basename($file)) . "</a><br>";
                        }
                        if (empty($code_files)) echo "<p>Файлы кода не загружены.</p>";
                        ?>

                        <?php if(isset($_SESSION['username']) && $_SESSION['username'] === $meta['author']): ?>
                        <div class="upload-section">
                            <h4>Загрузить файлы кода</h4>
                            <form action="index.php?page=upload_code" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="project_id" value="<?= $id ?>">
                                <input type="file" name="code_files[]" required multiple>
                                <button type="submit">Загрузить код</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ИЗМЕНЕНО: Блок для версий -->
                    <div class="card">
                        <h3><span style="color: #28a745;">Версии проекта</span></h3>
                        <?php if(!empty($meta['versions'])): ?>
                            <?php foreach(array_reverse($meta['versions']) as $version): // Новые версии сверху ?>
                                <div class="version-item">
                                    <strong><?= htmlspecialchars($version['name']) ?></strong>
                                    <div class="version-meta">Опубликовано: <?= date('Y-m-d H:i', $version['timestamp']) ?></div>
                                    <p><?= nl2br(htmlspecialchars($version['notes'])) ?></p>
                                    <a href="<?= "data/projects/$id/builds/" . htmlspecialchars($version['name']) . "/" . htmlspecialchars($version['file']) ?>" class="download-button" download>Скачать</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Еще не опубликовано ни одной версии.</p>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['username']) && $_SESSION['username'] === $meta['author']): ?>
                        <div class="upload-section">
                            <h4>Опубликовать новую версию</h4>
                            <form action="index.php?page=publish_version" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="project_id" value="<?= $id ?>">
                                <input type="text" name="version_name" placeholder="Название версии (например, v1.0)" required>
                                <textarea name="version_notes" placeholder="Описание изменений в этой версии..." rows="3"></textarea>
                                <input type="file" name="build_file" required>
                                <button type="submit">Опубликовать</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_GET['view'])): 
                    $file_path = PROJECTS_DIR . "/$id/code/" . basename($_GET['view']);
                    if (file_exists($file_path)):
                        $file_content = file_get_contents($file_path);
                ?>
                <div class="card">
                    <h3>Содержимое: <?= htmlspecialchars(basename($_GET['view'])) ?></h3>
                    <pre><code><?= htmlspecialchars($file_content) ?></code></pre>
                </div>
                <?php endif; endif; ?>

                <!-- НОВЫЙ БЛОК: Комментарии -->
                <div class="card" id="comments">
                    <h3>Комментарии</h3>
                    <?php 
                    $comments = getProjectComments($id);
                    if (!empty($comments)):
                        foreach(array_reverse($comments) as $comment): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <span class="comment-author"><?= htmlspecialchars($comment['author']) ?></span>
                                <span class="comment-date"><?= date('Y-m-d H:i', $comment['timestamp']) ?></span>
                            </div>
                            <p><?= nl2br(htmlspecialchars($comment['text'])) ?></p>
                        </div>
                        <?php endforeach;
                    else: ?>
                        <p>Комментариев пока нет. Будьте первым!</p>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['username'])): ?>
                    <div class="upload-section">
                        <h4>Оставить комментарий</h4>
                        <form action="index.php?page=add_comment" method="post">
                            <input type="hidden" name="project_id" value="<?= $id ?>">
                            <textarea name="comment_text" rows="4" placeholder="Ваш комментарий..." required></textarea>
                            <button type="submit">Отправить</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <p style="margin-top: 20px;"><a href="index.php?page=login">Войдите</a>, чтобы оставить комментарий.</p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <p>Проект не найден.</p>
            <?php endif; ?>
        <?php 
            // КОД ДЛЯ ДРУГИХ СТРАНИЦ (register, login, create_project)
            elseif($page === 'register'): ?>
            <div class="card"><h2>Регистрация</h2><form method="post"><input type="text" name="username" placeholder="Имя пользователя" required><input type="password" name="password" placeholder="Пароль" required><button type="submit">Зарегистрироваться</button></form></div>
            <?php elseif($page === 'login'): ?>
            <div class="card"><h2>Вход</h2><form method="post"><input type="text" name="username" placeholder="Имя пользователя" required><input type="password" name="password" placeholder="Пароль" required><button type="submit">Войти</button></form></div>
            <?php elseif($page === 'create_project' && isset($_SESSION['username'])): ?>
            <div class="card"><h2>Создать проект</h2><form method="post"><input type="text" name="title" placeholder="Название проекта" required><textarea name="description" placeholder="Краткое описание проекта" rows="4"></textarea><button type="submit">Создать</button></form></div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// Полный код для блоков register/login/logout, если нужно
if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = getUsers();
    $username = $_POST['username'];
    if (isset($users[$username])) {
        $error = "Имя '$username' уже занято.";
    } else {
        $users[$username] = ['password' => password_hash($_POST['password'], PASSWORD_DEFAULT)];
        saveUsers($users);
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit;
    }
}
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = getUsers();
    $username = $_POST['username'];
    if (isset($users[$username]) && password_verify($_POST['password'], $users[$username]['password'])) {
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit;
    } else {
        $error = "Неверный логин или пароль.";
    }
}
if ($page === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>