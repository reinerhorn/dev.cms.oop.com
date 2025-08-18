<!-- payments.tpl.php -->
<h1>Zahlungen Verwaltung</h1>

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>Payment-ID</th>
            <th>Order-ID</th>
            <th>Betrag</th>
            <th>Status</th>
            <th>Methode</th>
            <th>Datum</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($payments)): ?>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                    <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                    <td><?php echo htmlspecialchars($payment['amount']); ?></td>
                    <td><?php echo htmlspecialchars($payment['status']); ?></td>
                    <td><?php echo htmlspecialchars($payment['method']); ?></td>
                    <td><?php echo htmlspecialchars($payment['date']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align:center;">Keine Zahlungen gefunden</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
