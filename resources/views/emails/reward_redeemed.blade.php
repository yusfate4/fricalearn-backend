<!DOCTYPE html>
<html>
<body>
    <h2>🎉 A Student Just Redeemed a Reward!</h2>
    <p><strong>Student Name:</strong> {{ $student->name }} ({{ $student->email }})</p>
    <p><strong>Reward Claimed:</strong> {{ $reward->title }}</p>
    <p><strong>Coins Spent:</strong> {{ $reward->cost_coins }}</p>
    <hr>
    <p>Please reach out to the student or fulfill this digital order in your Admin Dashboard.</p>
</body>
</html>