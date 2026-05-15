<?php
require_once 'includes/config.php';
require_login();
if (!is_role('admin')) { header('Location: login.php'); exit; }
$pageTitle = 'Admin Categories | Event Ethiopia';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name'] ?? '');
        if ($name) {
            $stmt = $mysqli->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
            $message = 'Category added.';
        }
    }
    if (isset($_POST['delete_category'])) {
        $id = (int) $_POST['delete_category'];
        $check = $mysqli->prepare("SELECT COUNT(*) total FROM events WHERE category_id=?");
        $check->bind_param('i', $id);
        $check->execute();
        $total = (int) ($check->get_result()->fetch_assoc()['total'] ?? 0);
        $check->close();
        if ($total > 0) {
            $error = 'Cannot delete category with events.';
        } else {
            $stmt = $mysqli->prepare("DELETE FROM categories WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $message = 'Category deleted.';
        }
    }
    if (isset($_POST['edit_category'])) {
        $id = (int) $_POST['edit_category'];
        $name = sanitize($_POST['edit_name'] ?? '');
        if ($name) {
            $stmt = $mysqli->prepare("UPDATE categories SET name=? WHERE id=?");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $stmt->close();
            $message = 'Category updated.';
        }
    }
}

$stmt = $mysqli->prepare("SELECT c.id,c.name,COUNT(e.id) event_count FROM categories c LEFT JOIN events e ON e.category_id=c.id GROUP BY c.id,c.name ORDER BY c.name ASC");
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitize($pageTitle) ?></title><link rel="stylesheet" href="css/style.css"></head><body>
<main class="page-content wrapper">
<section class="heading-bar"><h2>Categories</h2><a href="admin-dashboard.php" class="button button-alt">Back</a></section>
<?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
<div class="grid-2">
<div class="dashboard-card">
<h3>Add Category</h3>
<form method="post" class="form-grid">
<input type="text" name="name" placeholder="Category name" required>
<button class="button" name="add_category" value="1">Add</button>
</form>
</div>
<div class="dashboard-card">
<h3>All Categories</h3>
<table class="list-table"><thead><tr><th>Name</th><th>Events</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td>
<form method="post" style="display:flex; gap:8px;">
<input type="text" name="edit_name" value="<?= sanitize($row['name']) ?>" required>
<button class="button button-alt" name="edit_category" value="<?= (int) $row['id'] ?>">Save</button>
</form>
</td>
<td><?= (int) $row['event_count'] ?></td>
<td><form method="post"><button class="button button-alt" name="delete_category" value="<?= (int) $row['id'] ?>">Delete</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div></main></body></html>
