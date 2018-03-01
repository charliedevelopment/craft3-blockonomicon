// example-expander block JS

(function() {

var blocks = document.querySelectorAll('.block--example-expander .title-bar');
blocks.forEach(function(el) {
	el.addEventListener('click', function(e) {
		e.currentTarget.classList.toggle('collapsed');
		e.currentTarget.parentNode.querySelector('.content').classList.toggle('collapsed');
	})
});

})();