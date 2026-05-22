<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Monthly Progress Report for {{ $student->name }}</h2>
    <p>Here is your child's performance summary for <strong>{{ $report['month_name'] }}</strong>.</p>
    
    <ul>
        <li><strong>Average Score:</strong> {{ $report['average_score'] }}%</li>
        <li><strong>Pass Rate:</strong> {{ $report['pass_rate'] }}%</li>
    </ul>

    @if(count($report['strong_areas']) > 0)
        <h3 style="color: #2D5A27;">Areas of Excellence 🌟</h3>
        <ul>
            @foreach($report['strong_areas'] as $area)
                <li>{{ $area['topic'] }} ({{ $area['subject'] }}) - {{ $area['avg_score'] }}%</li>
            @endforeach
        </ul>
    @endif

    @if(count($report['weak_areas']) > 0)
        <h3 style="color: #D32F2F;">Areas Requiring Attention ⚠️</h3>
        <p>We noticed {{ $student->name }} is experiencing some difficulty with these topics:</p>
        <ul>
            @foreach($report['weak_areas'] as $area)
                <li>{{ $area['topic'] }} ({{ $area['subject'] }}) - {{ $area['avg_score'] }}%</li>
            @endforeach
        </ul>
        
        <div style="background-color: #f9f9f9; padding: 15px; border-left: 4px solid #F5A623; margin-top: 20px;">
            <h4>💡 Recommendation</h4>
            <p>To help them master these concepts quickly, we strongly recommend a 1-on-1 session with our specialist tutors.</p>
            <a href="https://fricalearn.com/parent/dashboard" style="display: inline-block; padding: 10px 20px; background-color: #2D5A27; color: white; text-decoration: none; border-radius: 5px;">Book a 1-on-1 Tutor</a>
        </div>
    @else
        <p style="color: #2D5A27; font-weight: bold;">{{ $student->name }} is doing exceptionally well! Keep up the great work.</p>
    @endif
    
    <p><br>Warm regards,<br>The FricaLearn Team</p>
</body>
</html>