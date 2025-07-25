<?php

session_start();
require_once '../includes/db.php';

// ---------- Guard: only super_admins can enter ----------
if (
    !isset($_SESSION['admin_username'], $_SESSION['admin_role']) ||
    $_SESSION['admin_role'] !== 'super_admin'
) {
    header("Location: ../adminpage/superadmin_admins.php");
    exit();
}

// ---------- CSRF ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_ok() {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ---------- Helpers ----------
function flash($type, $msg) {
    $_SESSION['flash'][$type][] = $msg;
}
function flashes($type) {
    if (empty($_SESSION['flash'][$type])) return [];
    $f = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $f;
}
function clean($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$current_admin_id = $_SESSION['admin_id'];
$current_admin_username = $_SESSION['admin_username'];

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role     = $_POST['role'] === 'super_admin' ? 'super_admin' : 'admin';

            if ($username === '' || $email === '' || $password === '') {
                flash('error', 'All fields are required.');
                break;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Invalid email address.');
                break;
            }

            try {
                $stmt = $conn->prepare("SELECT 1 FROM admin_users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    flash('error', 'Username or email already exists.');
                    break;
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin_users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $email, $hashed, $role);
                $stmt->execute();

                flash('success', "Admin \"$username\" created.");
            } catch (Exception $e) {
                error_log($e->getMessage());
                flash('error', 'System error while creating admin.');
            }
            break;

        case 'update_role':
            $admin_id = (int)($_POST['id'] ?? 0);
            $role     = $_POST['role'] === 'super_admin' ? 'super_admin' : 'admin';

            if ($admin_id === $current_admin_id) {
                flash('error', "You can't change your own role.");
                break;
            }

            try {
                $stmt = $conn->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
                $stmt->bind_param("si", $role, $admin_id);
                $stmt->execute();
                flash('success', 'Role updated.');
            } catch (Exception $e) {
                error_log($e->getMessage());
                flash('error', 'System error while updating role.');
            }
            break;

        case 'reset_password':
            $admin_id = (int)($_POST['id'] ?? 0);
            $new_pass = trim($_POST['new_password'] ?? '');

            if ($new_pass === '') {
                flash('error', 'New password is required.');
                break;
            }
            if ($admin_id === $current_admin_id) {
                flash('error', "You can't reset your own password here — use Profile Settings.");
                break;
            }

            try {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $admin_id);
                $stmt->execute();
                flash('success', 'Password reset successfully.');
            } catch (Exception $e) {
                error_log($e->getMessage());
                flash('error', 'System error while resetting password.');
            }
            break;

        case 'delete':
            $admin_id = (int)($_POST['id'] ?? 0);

            if ($admin_id === $current_admin_id) {
                flash('error', "You can't delete yourself.");
                break;
            }

            try {
                $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                flash('success', 'Admin deleted.');
            } catch (Exception $e) {
                error_log($e->getMessage());
                flash('error', 'System error while deleting admin.');
            }
            break;

        default:
            flash('error', 'Unknown action.');
    }

    header("Location: superadmin_admins.php");
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('error', 'Invalid CSRF token.');
    header("Location: superadmin_admins.php");
    exit();
}

// ---------- Load all admins ----------
$all_admins = [];
try {
    $res = $conn->query("SELECT id, username, email, role, created_at FROM admin_users ORDER BY role DESC, username ASC");
    while ($row = $res->fetch_assoc()) {
        $all_admins[] = $row;
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    flash('error', 'Unable to load admins.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Super Admin • Manage Admins</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png">
</head>
<body class="min-h-screen bg-gray-100 text-gray-800">
  <header class="bg-white shadow p-4 flex items-center justify-between">
    <h1 class="text-xl font-bold">Super Admin &middot; Manage Admin Users</h1>
    <div class="flex items-center gap-3">
      <span class="text-sm">Logged in as <strong><?= clean($current_admin_username) ?></strong> (super admin)</span>
      <a href="admindashboard.php" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Back to Dashboard</a>
      <a href="logout.php" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">Logout</a>
    </div>
  </header>

  <main class="max-w-6xl mx-auto py-6 px-4">
    <!-- Flash Messages -->
    <?php foreach (flashes('success') as $m): ?>
      <div class="mb-3 p-3 rounded bg-green-100 text-green-700 border border-green-300"><?= clean($m) ?></div>
    <?php endforeach; ?>
    <?php foreach (flashes('error') as $m): ?>
      <div class="mb-3 p-3 rounded bg-red-100 text-red-700 border border-red-300"><?= clean($m) ?></div>
    <?php endforeach; ?>

    <!-- Create Admin -->
    <section class="bg-white rounded shadow p-5 mb-8">
      <h2 class="text-lg font-semibold mb-4">Create New Admin</h2>
      <form method="POST" class="grid md:grid-cols-2 gap-4">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="create">

        <div>
          <label class="block text-sm font-medium mb-1">Username</label>
          <input type="text" name="username" class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input type="email" name="email" class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Password</label>
          <input type="password" name="password" class="w-full border rounded px-3 py-2" required>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Role</label>
          <select name="role" class="w-full border rounded px-3 py-2">
            <option value="admin">Admin</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
            <i class="fa fa-plus mr-1"></i> Create Admin
          </button>
        </div>
      </form>
    </section>

    <!-- Admins Table -->
    <section class="bg-white rounded shadow p-5">
      <h2 class="text-lg font-semibold mb-4">All Admins</h2>

      <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-3 py-2 text-left">#</th>
              <th class="px-3 py-2 text-left">Username</th>
              <th class="px-3 py-2 text-left">Email</th>
              <th class="px-3 py-2 text-left">Role</th>
              <th class="px-3 py-2 text-left">Created</th>
              <th class="px-3 py-2 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($all_admins)): ?>
              <tr><td colspan="6" class="p-4 text-center text-gray-500">No admins found.</td></tr>
            <?php else: ?>
              <?php foreach ($all_admins as $i => $a): ?>
                <tr class="border-t">
                  <td class="px-3 py-2"><?= $i + 1 ?></td>
                  <td class="px-3 py-2"><?= clean($a['username']) ?></td>
                  <td class="px-3 py-2"><?= clean($a['email']) ?></td>
                  <td class="px-3 py-2">
                    <!-- Change role form -->
                    <?php if ((int)$a['id'] === (int)$current_admin_id): ?>
                      <span class="italic text-gray-500"><?= clean($a['role']) ?> (you)</span>
                    <?php else: ?>
                      <form method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <select name="role" class="border rounded px-2 py-1 text-sm mr-2">
                          <option value="admin" <?= $a['role']==='admin'?'selected':'' ?>>Admin</option>
                          <option value="super_admin" <?= $a['role']==='super_admin'?'selected':'' ?>>Super Admin</option>
                        </select>
                        <button class="text-blue-600 hover:underline text-sm">Save</button>
                      </form>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-2"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                  <td class="px-3 py-2 text-center space-x-2">
                    <?php if ((int)$a['id'] !== (int)$current_admin_id): ?>
                      <!-- Reset password -->
                      <button onclick="document.getElementById('rpw-<?= $a['id'] ?>').classList.toggle('hidden')"
                        class="text-indigo-600 hover:underline text-sm">Reset Password</button>

                      <!-- Delete -->
                      <form method="POST" class="inline" onsubmit="return confirm('Delete this admin? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="text-red-600 hover:underline text-sm">Delete</button>
                      </form>
                    <?php else: ?>
                      <span class="text-gray-400 text-sm italic">No actions</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if ((int)$a['id'] !== (int)$current_admin_id): ?>
                  <tr id="rpw-<?= $a['id'] ?>" class="hidden bg-gray-50 border-t">
                    <td colspan="6" class="px-3 py-3">
                      <form method="POST" class="flex gap-3 items-center">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <input type="password" name="new_password" class="border rounded px-3 py-2 flex-1" placeholder="New password" required>
                        <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                          Save New Password
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
