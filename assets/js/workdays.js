const workHours = {};
const rows = document.querySelectorAll(".branch-hours tr");
rows.forEach((row) => {
	const tds = row.querySelectorAll("td");
	const dayName = row.getAttribute("data-day");
	const hours = tds[tds.length - 2].innerText.trim();

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
console.log(isOpen(currentDay, currentTime));
if (isOpen(currentDay, currentTime)) {
	const openDayTD = document.querySelector(`[data-open-day="${currentDay}"]`);
	if (isHoliday()) {
		openDayTD.innerHTML = '<span class="holiday-text">Почивен ден</span>';
	} else {
		openDayTD.classList.add("open-now");
		openDayTD.innerHTML = '<span class="open-text">Отворено</span>';
	}
}
function isHoliday() {
	const holidays = document.querySelector(".holidays");
	if (holidays) {
		const holidayDates = holidays.innerText;
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
}
