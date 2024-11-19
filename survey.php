<?php
// Database Connection
$conn = new mysqli('localhost', 'root', '', 'survey_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Database Tables (if not exists)
$conn->query("
CREATE TABLE IF NOT EXISTS surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text', 'multiple_choice') NOT NULL,
    options TEXT NULL,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
)");

$conn->query("
CREATE TABLE IF NOT EXISTS responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT NOT NULL,
    question_id INT NOT NULL,
    response_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
)");

// Handle Survey Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_survey'])) {
    $title = $_POST['title'];
    $conn->query("INSERT INTO surveys (title) VALUES ('$title')");
    $survey_id = $conn->insert_id;

    foreach ($_POST['questions'] as $question) {
        $text = $question['text'];
        $type = $question['type'];
        $options = isset($question['options']) ? json_encode(explode(',', $question['options'])) : null;
        $conn->query("INSERT INTO questions (survey_id, question_text, question_type, options) 
                      VALUES ('$survey_id', '$text', '$type', '$options')");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Feedback Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $survey_id = $_POST['survey_id'];
    foreach ($_POST['responses'] as $question_id => $response) {
        $conn->query("INSERT INTO responses (survey_id, question_id, response_text) 
                      VALUES ('$survey_id', '$question_id', '$response')");
    }
    echo "Feedback submitted successfully!";
    exit;
}

// Fetch Surveys for Display
$surveys = $conn->query("SELECT * FROM surveys");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Survey System</title>
	<link rel="stylesheet" href="dan.css">
</head>
<body>
    <h1>Survey System</h1>

    <h2>Create a New Survey</h2>
    <form method="POST">
        <label>Survey Title:</label>
        <input type="text" name="title" required>
        <div id="questions">
            <h3>Questions</h3>
            <div>
                <label>Question Text:</label>
                <input type="text" name="questions[0][text]" required>
                <label>Type:</label>
                <select name="questions[0][type]" onchange="toggleOptions(this)">
                    <option value="text">Text</option>
                    <option value="multiple_choice">Multiple Choice</option>
                </select>
                <div class="options" style="display:none;">
                    <label>Options (comma-separated):</label>
                    <input type="text" name="questions[0][options]">
                </div>
            </div>
        </div>
        <button type="button" onclick="addQuestion()">Add Question</button>
        <button type="submit" name="create_survey">Create Survey</button>
    </form>

    <!-- View Surveys -->
    <h2>Available Surveys</h2>
    <?php while ($survey = $surveys->fetch_assoc()): ?>
        <h3><?php echo $survey['title']; ?></h3>
        <form method="POST">
            <input type="hidden" name="survey_id" value="<?php echo $survey['id']; ?>">
            <?php
            $questions = $conn->query("SELECT * FROM questions WHERE survey_id=" . $survey['id']);
            while ($question = $questions->fetch_assoc()):
            ?>
                <p><?php echo $question['question_text']; ?></p>
                <?php if ($question['question_type'] == 'text'): ?>
                    <input type="text" name="responses[<?php echo $question['id']; ?>]">
                <?php else: ?>
                    <?php foreach (json_decode($question['options']) as $option): ?>
                        <label>
                            <input type="radio" name="responses[<?php echo $question['id']; ?>]" value="<?php echo $option; ?>">
                            <?php echo $option; ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endwhile; ?>
            <button type="submit" name="submit_feedback">Submit Feedback</button>
        </form>
    <?php endwhile; ?>

    <!-- Analyze Responses -->
    <h2>Survey Analysis</h2>
    <?php
    $analysis = $conn->query("
        SELECT surveys.title AS survey_title, questions.question_text, responses.response_text, COUNT(*) AS count 
        FROM responses
        JOIN questions ON responses.question_id = questions.id
        JOIN surveys ON responses.survey_id = surveys.id
        GROUP BY responses.question_id, responses.response_text
    ");
    while ($row = $analysis->fetch_assoc()):
    ?>
        <p>
            Survey: <?php echo $row['survey_title']; ?><br>
            Question: <?php echo $row['question_text']; ?><br>
            Response: <?php echo $row['response_text']; ?><br>
            Count: <?php echo $row['count']; ?>
        </p>
    <?php endwhile; ?>

    <script>
        let questionCount = 1;
        function addQuestion() {
            const container = document.getElementById('questions');
            const div = document.createElement('div');
            div.innerHTML = `
                <label>Question Text:</label>
                <input type="text" name="questions[${questionCount}][text]" required>
                <label>Type:</label>
                <select name="questions[${questionCount}][type]" onchange="toggleOptions(this)">
                    <option value="text">Text</option>
                    <option value="multiple_choice">Multiple Choice</option>
                </select>
                <div class="options" style="display:none;">
                    <label>Options (comma-separated):</label>
                    <input type="text" name="questions[${questionCount}][options]">
                </div>
            `;
            container.appendChild(div);
            questionCount++;
        }

        function toggleOptions(select) {
            const optionsDiv = select.nextElementSibling.nextElementSibling;
            optionsDiv.style.display = select.value === 'multiple_choice' ? 'block' : 'none';
        }
    </script>
</body>
</html>
