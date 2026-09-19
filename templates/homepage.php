<section class="ybt-homepage">
	<style>
		/* YBT Brand Theme overrides */
		.ybt-hero { display:flex; align-items:center; justify-content:space-between; gap:24px; padding:48px 28px; background:linear-gradient(135deg, #047857 0%, #10b981 60%, #059669 100%); color:#fff; border-radius:18px; box-shadow:0 15px 35px rgba(16,185,129,0.2); }
		.ybt-hero .text { max-width:640px; }
		.ybt-hero h1 { margin:0 0 12px; font-size:32px; font-weight:800; color:#fff; }
		.ybt-hero p { margin:0 0 20px; color:rgba(255,255,255,0.9); font-size:1.1rem; }
		.ybt-cta { display:inline-block; padding:12px 24px; background:linear-gradient(135deg, #d4af37 0%, #b59021 100%); color:#1e293b; border-radius:12px; text-decoration:none; font-weight:700; box-shadow:0 4px 14px rgba(212,175,55,0.3); transition:all 0.25s ease; }
		.ybt-cta:hover { transform:translateY(-2px); color:#fff; box-shadow:0 8px 22px rgba(212,175,55,0.4); }
		.ybt-gallery { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-top:24px; }
		.ybt-gallery img { width:100%; height:180px; object-fit:cover; border-radius:14px; display:block; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
		.ybt-sections { margin-top:28px; display:grid; grid-template-columns:1fr; gap:18px; }
		.ybt-card { background:rgba(255,255,255,0.9); padding:24px; border-radius:18px; border:1px solid rgba(255,255,255,0.6); box-shadow:0 8px 30px rgba(0,0,0,0.04); backdrop-filter:none; }
		.ybt-steps { counter-reset:step; }
		.ybt-step { display:flex; gap:14px; align-items:flex-start; margin-bottom:16px; }
		.ybt-step .num { width:40px; height:40px; background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; margin-top:2px; flex-shrink:0; }
		.ybt-auth-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:24px; margin-top:28px; }
		.ybt-input { width:100%; padding:12px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.95rem; }
		.ybt-input:focus { border-color:#10b981; outline:none; box-shadow:0 0 0 3px rgba(16,185,129,0.25); }
		.ybt-btn { display:inline-block; padding:12px 22px; border-radius:10px; border:none; cursor:pointer; font-weight:600; }
		.ybt-btn-primary { background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; }
		.ybt-alert { padding:10px 14px; border-radius:8px; margin-top:12px; display:none; }
		.ybt-alert-success { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
		.ybt-alert-error { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
		.ybt-proof { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; margin-top:24px; }
		.ybt-proof .ybt-counter { text-align:center; padding:20px; border-radius:14px; background:#ecfdf5; border:1px solid #a7f3d0; font-weight:600; }
		.ybt-proof .ybt-stat-value { font-size:36px; font-weight:800; color:#047857; display:block; }
		.ybt-proof blockquote { margin:0; background:#fff; padding:20px; border-radius:14px; box-shadow:0 4px 15px rgba(0,0,0,0.04); font-style:italic; border-left:4px solid #d4af37; }
		.ybt-services-grid, .ybt-pricing-grid, .ybt-feature-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; margin-top:18px; }
		.ybt-service-card { border:1px solid #e2e8f0; border-radius:14px; padding:20px; background:#fff; }
		.ybt-pricing-card { border:2px solid #10b981; border-radius:16px; padding:24px; background:#ecfdf5; }
		.ybt-pricing-card h3 { margin-top:0; color:#047857; }
		.ybt-pricing-card ul { padding-left:18px; margin:12px 0 0; }
		.ybt-price-value { font-size:32px; font-weight:800; color:#047857; margin:0; }
		.ybt-feature-card { border:1px solid #e2e8f0; border-radius:14px; padding:20px; background:#fff; }
		.ybt-faq { margin-top:24px; }
		.ybt-faq details { border:1px solid #e2e8f0; border-radius:12px; padding:14px 18px; margin-bottom:12px; background:#fff; }
		.ybt-contact-cta { margin-top:32px; padding:32px; border-radius:18px; background:linear-gradient(135deg, #047857 0%, #10b981 100%); color:#fff; display:flex; flex-direction:column; gap:14px; box-shadow:0 15px 35px rgba(16,185,129,0.2); }
		.ybt-contact-cta a { color:#fef3c7; font-weight:700; text-decoration:underline; }
		.ybt-hero img { width:320px; border-radius:14px; object-fit:cover; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
		@media (max-width:700px){ .ybt-hero { flex-direction:column; } .ybt-hero img{ max-width:100%; } }
	</style>

	<div class="ybt-hero">
		<div class="text">
			<h1>We pack and prepare your parcels for shipping</h1>
			<p>Fast, secure packing services for incoming parcels. Select your received parcels and request packing — we'll handle the rest and prepare them for shipment.</p>
			<a class="ybt-cta" href="javascript:void(0)" onclick="if(typeof openPackingRequestModal === 'function'){ openPackingRequestModal(); }else{ location.href='/?s=packing'; }">Request Packing</a>
		</div>
		<img src="https://images.unsplash.com/photo-1542831371-d531d36971e6?w=700&q=65&auto=format&fit=crop" alt="Packing" width="320" height="320" fetchpriority="high" decoding="async">
	</div>

	<!-- Social proof & stats -->
	<div class="ybt-sections">
		<div class="ybt-card">
			<h2>Trusted by global shoppers</h2>
			<p>Thousands of forwarding customers depend on us to consolidate and prep their parcels every month.</p>
			<div class="ybt-proof">
				<div class="ybt-counter">
					<span class="ybt-stat-value">15k+</span>
					<span>boxes packed this year</span>
				</div>
				<div class="ybt-counter">
					<span class="ybt-stat-value">24h</span>
					<span>average turnaround</span>
				</div>
				<div class="ybt-counter">
					<span class="ybt-stat-value">98%</span>
					<span>on-time dispatch rate</span>
				</div>
				<blockquote>
					“YBT keeps my reselling business on schedule. Their packing photos and notifications are the most reliable I’ve used.”
					<footer style="margin-top:8px;font-style:normal;color:#475467;">— Amira, EU fashion seller</footer>
				</blockquote>
			</div>
		</div>
	</div>

	<div class="ybt-gallery" aria-hidden="false" style="margin-top:20px;">
		<img src="https://images.unsplash.com/photo-1520975912223-1f1a0b6f7a5b?w=600&q=65&auto=format&fit=crop" alt="boxes" loading="lazy" decoding="async">
		<img src="https://images.unsplash.com/photo-1518091043644-c1d4457512c6?w=600&q=65&auto=format&fit=crop" alt="team" loading="lazy" decoding="async">
		<img src="https://images.unsplash.com/photo-1519741490695-2f8b6d0f1c9a?w=600&q=65&auto=format&fit=crop" alt="warehouse" loading="lazy" decoding="async">
	</div>

	<div class="ybt-sections">
		<div class="ybt-card">
			<h2>About us</h2>
			<p>We provide packing services for received parcels at our warehouse. Our team inspects, secures and labels each parcel so you can ship with confidence. We handle fragile items, bulk orders and custom requests.</p>
		</div>

		<div class="ybt-card">
			<h2>How to use the site</h2>
			<div class="ybt-steps">
				<div class="ybt-step">
					<div class="num">1</div>
					<div>
						<strong>Select received parcels</strong>
						<p style="margin:6px 0 0;color:#555;">On the "Received" page, tick the parcels you want packed.</p>
					</div>
				</div>

				<div class="ybt-step">
					<div class="num">2</div>
					<div>
						<strong>Request packing</strong>
						<p style="margin:6px 0 0;color:#555;">Click "Request Packing for Selected" — the packing form will open. Provide shipping details and submit.</p>
					</div>
				</div>

				<div class="ybt-step">
					<div class="num">3</div>
					<div>
						<strong>We pack and notify you</strong>
						<p style="margin:6px 0 0;color:#555;">Our team processes your request and updates the parcel status. You receive confirmation when ready.</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Services overview -->
	<div class="ybt-sections">
		<div class="ybt-card">
			<h2>What we can handle</h2>
			<div class="ybt-services-grid">
				<div class="ybt-service-card">
					<h3>Fragile & luxury</h3>
					<p>Padded wrapping, double-boxing, and photo proof before sealing.</p>
				</div>
				<div class="ybt-service-card">
					<h3>Bulk consolidation</h3>
					<p>Combine dozens of parcels into export-ready boxes with itemized manifests.</p>
				</div>
				<div class="ybt-service-card">
					<h3>Regulated goods</h3>
					<p>Battery, cosmetics, and apparel compliance checks aligned with carrier rules.</p>
				</div>
				<div class="ybt-service-card">
					<h3>Custom instructions</h3>
					<p>Personal notes, gift cards, or repackaging in branded material on request.</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Pricing snapshot -->
    <div class="ybt-sections">
		<div class="ybt-card">
			<h2>Simple pricing</h2>
			<p>Final quotes depend on size and materials. Use the table below as a quick guide and request precise pricing from your dashboard.</p>
			<div class="ybt-pricing-grid">
				<div class="ybt-pricing-card">
					<h3>Standard Box</h3>
					<p class="ybt-price-value">$8</p>
					<small>per parcel</small>
					<ul>
						<li>Up to 5kg</li>
						<li>Bubble wrap + filler</li>
						<li>Status photos</li>
					</ul>
				</div>
				<div class="ybt-pricing-card" style="border-color:#f59e0b;background:#fff7e6;">
					<h3>Consolidated</h3>
					<p class="ybt-price-value">$15</p>
					<small>per large box</small>
					<ul>
						<li>Up to 20kg</li>
						<li>Custom dividers</li>
						<li>Insurance-ready labeling</li>
					</ul>
				</div>
				<div class="ybt-pricing-card" style="border-color:#10b981;background:#ecfdf5;">
					<h3>Priority</h3>
					<p class="ybt-price-value">$25</p>
					<small>per order</small>
					<ul>
						<li>Same-day turnaround</li>
						<li>Dedicated agent</li>
						<li>Email progress updates</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- Feature highlight -->
	<div class="ybt-sections">
		<div class="ybt-card">
			<h2>Tools you unlock after login</h2>
			<div class="ybt-feature-grid">
				<div class="ybt-feature-card">
					<h3>Live dashboard</h3>
					<p>Track parcel states, submit packing requests, and monitor warehouse activity in real time.</p>
				</div>
				<div class="ybt-feature-card">
					<h3>Analytics & insights</h3>
					<p>Dive into shipment volumes, storage days, and carrier performance using the built-in analytics page.</p>
				</div>
				<div class="ybt-feature-card">
					<h3>Chat & notifications</h3>
					<p>Message warehouse agents, receive SMS status alerts, and keep everything synced.</p>
				</div>
				<div class="ybt-feature-card">
					<h3>Security controls</h3>
					<p>Two-factor prompts, session history, and export/import tooling keep your data portable and secure.</p>
				</div>
			</div>
		</div>
	</div>

	<!-- FAQ -->
	<div class="ybt-sections">
		<div class="ybt-card">
			<h2>Frequently asked questions</h2>
			<div class="ybt-faq">
				<details>
					<summary>Can I ship to any carrier?</summary>
					<p>Yes. We provide neutral packaging and paperwork so you can hand parcels to DHL, FedEx, Aramex, or local freight forwarders.</p>
				</details>
				<details>
					<summary>Do you store parcels?</summary>
					<p>Included storage covers 30 days after arrival. Extended storage is available for a small weekly fee inside your dashboard.</p>
				</details>
				<details>
					<summary>What proof do I receive?</summary>
					<p>We attach timestamped photos, weight logs, and packing material lists to every request before marking it packed.</p>
				</details>
				<details>
					<summary>How do I reach support?</summary>
					<p>Use the in-app chat or email support@ybtpacker.com for urgent help.</p>
				</details>
			</div>
		</div>
	</div>

	<!-- Auth Section: Login + Register -->
	<div class="ybt-sections">
		<div class="ybt-card">
			<h2>Access Your Account</h2>
			<div class="ybt-auth-grid">
				<!-- Login -->
				<div>
					<h3 style="margin-top:0;">Login</h3>
					<form id="ybt-home-login-form" method="POST">
						<label>Username</label>
						<input type="text" id="home-login-username" class="ybt-input" required>
						<label style="margin-top:8px;">Password</label>
						<input type="password" id="home-login-password" class="ybt-input" required>
						<div style="margin:8px 0;">
							<label>
								<input type="checkbox" id="home-login-remember"> Remember me
							</label>
						</div>
						<button type="submit" class="ybt-btn ybt-btn-primary">Login</button>
						<div id="home-login-message" class="ybt-alert"></div>
					</form>
				</div>

				<!-- Register -->
				<div>
					<h3 style="margin-top:0;">Register</h3>
					<form id="ybt-home-register-form" method="POST">
						<label>Username</label>
						<input type="text" id="home-register-username" class="ybt-input" required>
						<label style="margin-top:8px;">Email</label>
						<input type="email" id="home-register-email" class="ybt-input" required>
						<label style="margin-top:8px;">Password</label>
						<input type="password" id="home-register-password" class="ybt-input" required>
						<label style="margin-top:8px;">Confirm Password</label>
						<input type="password" id="home-register-confirm" class="ybt-input" required>
						<button type="submit" class="ybt-btn ybt-btn-primary" style="margin-top:8px;">Register</button>
						<div id="home-register-message" class="ybt-alert"></div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Contact CTA -->
	<div class="ybt-contact-cta">
		<div>
			<h2 style="margin:0;">Need a custom packing plan?</h2>
			<p style="margin:6px 0 0;">Email <a href="mailto:support@ybtpacker.com">support@ybtpacker.com</a> or use the in-app chat for instant answers.</p>
		</div>
		<div>
			<span>Business hours: Mon–Sat, 8am–10pm GMT</span>
		</div>
	</div>

	<script>
	(function(){
		var ajaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
		var nonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo wp_create_nonce('ybt_nonce'); ?>';

		// Login
		var loginForm = document.getElementById('ybt-home-login-form');
		if (loginForm) {
			loginForm.addEventListener('submit', function(e){
				e.preventDefault();
				var u = document.getElementById('home-login-username').value;
				var p = document.getElementById('home-login-password').value;
				var r = document.getElementById('home-login-remember').checked ? '1' : '0';
				var msg = document.getElementById('home-login-message');
				msg.style.display = 'none'; msg.className = 'ybt-alert'; msg.textContent = '';

				var fd = new FormData();
				fd.append('action', 'ybt_ajax_login');
				fd.append('nonce', nonce);
				fd.append('username', u);
				fd.append('password', p);
				fd.append('remember', r);

				fetch(ajaxUrl, { method:'POST', body: fd, credentials: 'same-origin' })
					.then(function(res){ return res.json(); })
					.then(function(data){
						if (data && data.success) {
							window.location.href = '<?php echo esc_url( home_url('/dashboard/') ); ?>';
						} else {
							msg.classList.add('ybt-alert-error');
							msg.textContent = data && data.data ? data.data : 'Login failed';
							msg.style.display = 'block';
						}
					})
					.catch(function(){
						msg.classList.add('ybt-alert-error');
						msg.textContent = 'Network error. Please try again.';
						msg.style.display = 'block';
					});
			});
		}

		// Register
		var regForm = document.getElementById('ybt-home-register-form');
		if (regForm) {
			regForm.addEventListener('submit', function(e){
				e.preventDefault();
				var u = document.getElementById('home-register-username').value;
				var em = document.getElementById('home-register-email').value;
				var p = document.getElementById('home-register-password').value;
				var c = document.getElementById('home-register-confirm').value;
				var msg = document.getElementById('home-register-message');
				msg.style.display = 'none'; msg.className = 'ybt-alert'; msg.textContent = '';

				var fd = new URLSearchParams();
				fd.append('action', 'ybt_register_user');
				fd.append('nonce', nonce);
				fd.append('username', u);
				fd.append('email', em);
				fd.append('password', p);
				fd.append('confirm_password', c);

				fetch(ajaxUrl, { method:'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' }, body: fd.toString(), credentials: 'same-origin' })
					.then(function(res){ return res.json(); })
					.then(function(data){
						if (data && data.success) {
							msg.classList.add('ybt-alert-success');
							msg.textContent = data.data || 'Registration successful! Redirecting...';
							msg.style.display = 'block';
							setTimeout(function(){ window.location.href = '<?php echo esc_url( home_url('/dashboard/') ); ?>'; }, 500);
						} else {
							msg.classList.add('ybt-alert-error');
							msg.textContent = data && data.data ? data.data : 'Registration failed';
							msg.style.display = 'block';
						}
					})
					.catch(function(){
						msg.classList.add('ybt-alert-error');
						msg.textContent = 'Network error. Please try again.';
						msg.style.display = 'block';
					});
			});
		}
	})();
	</script>
</section>
