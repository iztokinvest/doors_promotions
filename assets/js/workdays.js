const workHours = {};

// Get all the rows from the table
const rows = document.querySelectorAll(".branch-hours tr");

// Loop through each row and extract the day and hours
rows.forEach((row) => {
	const dayName = row.querySelector("td:first-child").innerText.trim();
	const hours = row.querySelector("td:last-child").innerText.trim();

	if (hours === "Затворено") {
		workHours[dayName] = { start: null, end: null }; // Closed day
	} else {
		// Split the hours string into start and end time
		const [start, end] = hours.split("-").map((time) => time.trim());
		workHours[dayName] = { start, end };
	}
});

// Example of checking if the current time is within the work hours
const today = new Date();
const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const currentDay = dayNames[today.getDay()];
const currentTime = today.toTimeString().slice(0, 5); // Get time in HH:MM format

console.log(currentDay, currentTime);

function isOpen(day, time) {
	const hours = workHours[day];
	if (!hours.start || !hours.end) return false; // If the day is closed
	return time >= hours.start && time <= hours.end;
}

if (isOpen(currentDay, currentTime)) {
	document.querySelector(`.${currentDay}`).innerHTML += '<span style="color: green;"> Отворено</span>';
}
