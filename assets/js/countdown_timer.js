document.addEventListener("DOMContentLoaded", function () {
	console.log("DOM fully loaded and parsed on client side");

	function countdownTimer() {
		const daysElement = document.getElementById("timer-days");
		const hoursElement = document.getElementById("timer-hours");
		const minutesElement = document.getElementById("timer-minutes");
		const secondsElement = document.getElementById("timer-seconds");

		const endDateStr =
			daysElement.getAttribute("data-end-date") ||
			hoursElement.getAttribute("data-end-date") ||
			minutesElement.getAttribute("data-end-date") ||
			secondsElement.getAttribute("data-end-date");

		if (!endDateStr) {
			console.log("End date not found");
			return;
		}

		const endDate = new Date(endDateStr);
		endDate.setHours(23, 59, 59, 999); // Set end of day
		const endTime = endDate.getTime();

		function getPluralForm(value, single, plural) {
			return value === 1 ? single : plural;
		}

		function updateCountdown() {
			const now = new Date().getTime();
			const distance = endTime - now;

			const days = Math.floor(distance / (1000 * 60 * 60 * 24));
			const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
			const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
			const seconds = Math.floor((distance % (1000 * 60)) / 1000);

			if (daysElement) {
				daysElement.innerHTML =
					days > 0
						? `<span id="days-number">${days}</span><span id="days-title">${getPluralForm(
								days,
								" <span id='days-title'>ден</span>",
								" <span id='days-title'>дни</span>"
						  )}</span>`
						: "";
			}
			if (hoursElement) {
				hoursElement.innerHTML =
					hours > 0
						? `<span id="hours-number">${hours}</span><span id="hours-title">${getPluralForm(
								hours,
								" <span id='hours-title'>час</span>",
								" <span id='hours-title'>часа</span>"
						  )}</span>`
						: "";
			}
			if (minutesElement) {
				minutesElement.innerHTML =
					minutes > 0
						? `<span id="minutes-number">${minutes}</span><span id="minutes-title">${getPluralForm(
								minutes,
								" <span id='minutes-title'>минута</span>",
								" <span id='minutes-title'>минути</span>"
						  )}</span>`
						: "";
			}
			if (secondsElement) {
				secondsElement.innerHTML = `<span id="seconds-number">${seconds}</span><span id="seconds-title">${getPluralForm(
					seconds,
					" <span id='seconds-title'>секунда</span>",
					" <span id='seconds-title'>секунди</span>"
				)}</span>`;
			}

			if (distance < 0) {
				clearInterval(x);
				secondsElement.innerHTML = "";
			}
		}

		const x = setInterval(updateCountdown, 1000);
		updateCountdown();
	}

	countdownTimer();
});
