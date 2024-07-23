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
						? `<span class="days-number">${days}</span><span class="days-title">${getPluralForm(
								days,
								" <span class='days-title'>ден</span>",
								" <span class='days-title'>дни</span>"
						  )}</span>`
						: "";
			}
			if (hoursElement) {
				hoursElement.innerHTML =
					hours > 0
						? `<span class="hours-number">${hours}</span><span class="hours-title">${getPluralForm(
								hours,
								" <span class='hours-title'>час</span>",
								" <span class='hours-title'>часа</span>"
						  )}</span>`
						: "";
			}
			if (minutesElement) {
				minutesElement.innerHTML =
					minutes > 0
						? `<span class="minutes-number">${minutes}</span><span class="minutes-title">${getPluralForm(
								minutes,
								" <span class='minutes-title'>минута</span>",
								" <span class='minutes-title'>минути</span>"
						  )}</span>`
						: "";
			}
			if (secondsElement) {
				secondsElement.innerHTML = `<span class="seconds-number">${seconds}</span><span class="seconds-title">${getPluralForm(
					seconds,
					" <span class='seconds-title'>секунда</span>",
					" <span class='seconds-title'>секунди</span>"
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
