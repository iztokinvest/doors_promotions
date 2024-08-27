const workHoursSections = document.querySelectorAll(".workdays");

if (workHoursSections) {
	workHoursSections.forEach((section) => {
		const workHours = {};
		const rows = section.querySelectorAll("tr");

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

		if (isOpen(currentDay, currentTime)) {
			const openDayTD = section.querySelector(`[data-open-day="${currentDay}"]`);
			const parentTr = openDayTD.closest("tr");

			if (isHoliday(section)) {
				parentTr.classList.add("holiday-now");
				openDayTD.innerHTML = "Почивен ден";
			} else {
				parentTr.classList.add("open-now");
				openDayTD.innerHTML = "Отворено";
			}
		}
	});
}

function isHoliday(section) {
	const holidays = section.querySelector(".holidays");
	if (holidays) {
		const holidayDates = holidays.innerText;
		const dateStrings = holidayDates.split(",").map((date) => date.trim());
		const datesArray = dateStrings.map((dateString) => {
			const [day, month, year] = dateString.split(".").map(Number);
			return new Date(year, month - 1, day);
		});
		for (const date of datesArray) {
			const dateMidnight = new Date(date.setHours(0, 0, 0, 0));
			const todayMidnight = new Date(new Date().setHours(0, 0, 0, 0));
			if (dateMidnight.getTime() === todayMidnight.getTime()) {
				return true;
			}
		}
	}
	return false;
}
