$(function(){
	function isMatch(candidate, text) {
		if (candidate.name.toLowerCase().includes(text))
			return true;

		if (candidate.username.toLowerCase().includes(text))
			return true;

		if (("" + candidate.skymanager_id).includes(text))
			return true;

		if (candidate.voting_id && ("" + candidate.voting_id).includes(text))
			return true;

		return false;
	}

	function createChild(candidate) {
		var li = document.createElement('li');
		li.className = 'candidate';

		var profileimgsect = document.createElement('div');
		profileimgsect.className = 'profile-icon';

		var profileimg = document.createElement('img');
		profileimg.src = 'https://www.gravatar.com/avatar/' + candidate.gravatar_hash + '.png?d=mp&s=64';
		profileimgsect.appendChild(profileimg);

		var profiletext = document.createElement('div');
		profiletext.className = 'profile';

		var profilename = document.createElement('h2');
		profilename.className = 'profile-name';
		profilename.textContent = candidate.name;

		var profileid = document.createElement('h4');
		profileid.className = 'profile-id';
		profileid.textContent = candidate.voting_id;
		if (!candidate.voting_id || candidate.delegate) {
			profiletext.className += ' ineligible';
			profileid.textContent += " (Ineligible)";
		}

		profiletext.appendChild(profilename);
		profiletext.appendChild(profileid);

		if (candidate.proxies) {
			var proxies = document.createElement('div');
			proxies.className = 'proxies';
			proxies.textContent = 'Proxy Votes: ' + candidate.proxies.replace(',', ', ');
			profiletext.appendChild(proxies);
		}


		li.appendChild(profileimgsect);
		li.appendChild(profiletext);

		li.addEventListener('click', function() {
			var csc = document.getElementById("selectedVoter");
			while (csc.firstChild) {
				csc.removeChild(csc.firstChild);
			}

			csc.appendChild(profileimgsect);
			csc.appendChild(profiletext);
			var smid = document.getElementById('voter-smid');
			if (smid) smid.value = candidate.skymanager_id;
			document.getElementById('voter-input').value = candidate.voting_id;
			document.getElementById('voter-searchbox').value = "";
			search('');
		});


		return li;
	}

	function search(text) {
		var list = document.createElement('ul');

		var matches = 0;
		for (var i = 0; text.length > 0 && i < voters.length; i++) {
			if (isMatch(voters[i], text.toLowerCase())) {
				list.appendChild(createChild(voters[i]));
				if (++matches > 4)
					break;
			}
		}

		var container = document.getElementById('voter-results');
		while (container.firstChild) {
			container.removeChild(container.firstChild);
		}

		if (matches)
			container.appendChild(list);
	}

	$('#voter-searchbox').bind('textInput input', function() { search(this.value); });
});
