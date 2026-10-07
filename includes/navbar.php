<nav class="actions">
    <a href="/arnold_jackies_store/dashboard.php">Dashboard</a>
    <a href="/arnold_jackies_store/pos/">New Sale</a>
    <a href="/arnold_jackies_store/products/">Inventory</a>
    <a href="/arnold_jackies_store/wallet/">GCash / Maya</a>
    <a href="/arnold_jackies_store/reports/">Reports</a>
    <a href="/arnold_jackies_store/expenses/">Expenses</a>

    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="/arnold_jackies_store/settings/">Settings</a>
    <?php endif; ?>
</nav>