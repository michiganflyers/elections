$(function(){
	function isMatch(candidate, text, ballot) {
		if (ballot !== undefined && candidate.position !== ballot && candidate.position !== undefined)
			return false;

		if (candidate.name.toLowerCase().includes(text))
			return true;

		if (candidate.username.toLowerCase().includes(text))
			return true;

		if (("" + candidate.skymanager_id).includes(text))
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
		profileid.textContent = candidate.skymanager_id;

		profiletext.appendChild(profilename);
		profiletext.appendChild(profileid);

		li.appendChild(profileimgsect);
		li.appendChild(profiletext);

		li.addEventListener('click', function() {
			var csc = document.getElementById("selectedCandidate");
			while (csc.firstChild) {
				csc.removeChild(csc.firstChild);
			}

			csc.appendChild(profileimgsect);
			csc.appendChild(profiletext);
			document.getElementById('candidate-input').value = candidate.skymanager_id;
			document.getElementById('searchbox').value = "";
			search('');
		});


		return li;
	}

	function search(text, ballot) {
		var list = document.createElement('ul');

		var matches = 0;
		for (var i = 0; text.length > 0 && i < search_list.length; i++) {
			if (isMatch(search_list[i], text.toLowerCase(), ballot)) {
				list.appendChild(createChild(search_list[i]));
				if (++matches > 4)
					break;
			}
		}

		var container = document.getElementById('results');
		while (container.firstChild) {
			container.removeChild(container.firstChild);
		}

		if (matches)
			container.appendChild(list);
	}

	function defaultLoad(candidate_id) {
		var candidate = search_list.filter(c => +c.skymanager_id == candidate_id);
		if (candidate.length > 0)
			candidate = candidate[0];

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
		profileid.textContent = candidate.skymanager_id;

		profiletext.appendChild(profilename);
		profiletext.appendChild(profileid);

		var csc = document.getElementById("selectedCandidate");
		while (csc.firstChild) {
			csc.removeChild(csc.firstChild);
		}

		csc.appendChild(profileimgsect);
		csc.appendChild(profiletext);
		document.getElementById('candidate-input').value = candidate.skymanager_id;
		document.getElementById('searchbox').value = "";
	}

	//$('#searchbox').bind('change keypress keydown keyup', function() { search(this.value); });
	$('#searchbox').bind('textInput input', function() { search(this.value, this.form.ballot?.value ?? undefined); });

	var initialProfile = +document.getElementById('candidate-input').value;
	if (initialProfile != 0)
		defaultLoad(initialProfile);
});
