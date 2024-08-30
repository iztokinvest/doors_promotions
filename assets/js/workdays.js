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
		const dayNames = [
			"Sunday",
			"Monday",
			"Tuesday",
			"Wednesday",
			"Thursday",
			"Friday",
			"Saturday",
			"Monday-Friday",
		];
		const currentDay = dayNames[today.getDay()];
		const currentTime = today.toTimeString().slice(0, 5);

		function isOpen(day, time) {
			const hours = workHours[day];
			if (!hours.start || !hours.end) return false;
			return time >= hours.start && time <= hours.end;
		}

		function timeDifferenceInMinutes(time1, time2) {
			const [hours1, minutes1] = time1.split(":").map(Number);
			const [hours2, minutes2] = time2.split(":").map(Number);
			return (hours1 - hours2) * 60 + (minutes1 - minutes2);
		}

		function getStatusMessage(day, time) {
			const hours = workHours[day];
			if (!hours.start || !hours.end) return "Затворено";

			const minutesToOpen = timeDifferenceInMinutes(hours.start, time);
			const minutesToClose = timeDifferenceInMinutes(hours.end, time);

			if (minutesToOpen > 0 && minutesToOpen < 60) {
				return "Отваря скоро";
			} else if (minutesToClose > 0 && minutesToClose < 60) {
				return "Затваря скоро";
			} else if (isOpen(day, time)) {
				return "Отворено";
			}

			return "Затворено";
		}

		function isWithinDayRange(currentDay, range) {
			const days = {
				Monday: 1,
				Tuesday: 2,
				Wednesday: 3,
				Thursday: 4,
				Friday: 5,
				Saturday: 6,
				Sunday: 0,
			};
			const [startDay, endDay] = range.split("-");
			const currentDayIndex = days[currentDay];
			const startDayIndex = days[startDay];
			const endDayIndex = days[endDay];

			// Handle wrap-around for ranges like Monday-Friday
			if (startDayIndex <= endDayIndex) {
				return currentDayIndex >= startDayIndex && currentDayIndex <= endDayIndex;
			} else {
				return currentDayIndex >= startDayIndex || currentDayIndex <= endDayIndex;
			}
		}

		for (const day in workHours) {
			if (day === currentDay || (day.includes("-") && isWithinDayRange(currentDay, day))) {
				const statusMessage = getStatusMessage(day, currentTime);
				const openDayTD = section.querySelector(`[data-open-day="${day}"]`);
				const parentTr = openDayTD.closest("tr");

				if (statusMessage === "Отворено" && isHoliday(section)) {
					parentTr.classList.add("holiday-now");
					openDayTD.innerHTML = "Почивен ден";
				} else {
					parentTr.classList.add(statusMessage === "Отворено" ? "open-now" : "closed-now");
					openDayTD.innerHTML = statusMessage;
				}
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
