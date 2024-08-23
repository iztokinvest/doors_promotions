const workHours = {};

const rows = document.querySelectorAll(".branch-hours tr");

rows.forEach((row) => {
	const dayName = row.getAttribute("data-day");
	const hours = row.querySelector("td:last-child").innerText.trim();

	if (hours === "Затворено") {
		workHours[dayName] = { start: null, end: null };
	} else {
		const [start, end] = hours.split("-").map((time) => time.trim());
		workHours[dayName] = { start, end };
	}
});

const today = new Date();
const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const currentDay = dayNames[today.getDay()];
const currentTime = today.toTimeString().slice(0, 5);

function isOpen(day, time) {
	const hours = workHours[day];

	if (!hours.start || !hours.end) return false;
	return time >= hours.start && time <= hours.end;
}

if (isOpen(currentDay, currentTime)) {
	if (isHoliday()) {
		document.querySelector(`.${currentDay}`).innerHTML += '<span class="holiday-text"> Почивен ден</span>';
	} else {
		document.querySelector(`.${currentDay}`).classList.add("open-now");
		document.querySelector(`.${currentDay}`).innerHTML += '<span class="open-text"> Отворено</span>';
	}
}

function isHoliday() {
	const holidayDates = document.querySelector(".holidays").innerText;
	const dateStrings = holidayDates.split(",").map((date) => date.trim());
	const datesArray = dateStrings.map((dateString) => {
		const [day, month, year] = dateString.split(".").map(Number);
		return new Date(year, month - 1, day);
	});

	for (const date of datesArray) {
		const dateMidnight = new Date(date.setHours(0, 0, 0, 0));
		const todayMidnight = new Date(today.setHours(0, 0, 0, 0));

		if (dateMidnight.getTime() === todayMidnight.getTime()) {
			return true;
		}
	}
}
