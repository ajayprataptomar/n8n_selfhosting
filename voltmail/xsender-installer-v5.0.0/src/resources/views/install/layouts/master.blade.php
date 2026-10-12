<!DOCTYPE html>
<html lang="{{App::getLocale()}}" class="sr" data-sidebar="open">

  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{csrf_token()}}" />
    <title>
      {{@config('installer.app_name')}}-{{@$title}}
   </title>


    <link  href="{{asset('assets/theme/global/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css" />
    <style >
      .main{
            min-height: 100vh;
            padding: 30px 0;
            background: var(--color-white);
            background-size: cover;
            background-repeat: no-repeat;
            position: relative;
            z-index: 1;
        }
        .main::before{
          content: '';
          position: absolute;
          left: 10px;
          top: 10px;
          width: 300px;
          height: 300px;
          border-radius: 50%;
          background: rgba(255, 87, 34,0.5);
          filter: blur(170px);
          z-index: -1;
        }
        .main::after{
          content: '';
          position: absolute;
          right: 10px;
          bottom: 10px;
          width: 300px;
          height: 300px;
          border-radius: 50%;
          background: var(--color-primary-light-2);
          filter: blur(170px);
          z-index: -1;
        }
    </style>
    <link  href="{{asset('assets/theme/install/css/style.css')}}" rel="stylesheet" type="text/css"/>
    <link  href="{{asset('assets/theme/global/css/bootstrap-icons.min.css')}}" rel="stylesheet" type="text/css" />
    <link  href="{{asset('assets/theme/global/css/toastr.css')}}" rel="stylesheet" type="text/css" />
  
    @stack('styles')
    @stack('style-include')
  </head>
  <body>

    <main class="main d-flex flex-column justify-content-center align-items-center" id="main">
      <div class="text-center mb-5">
        <h4 class="text-dark">
            {{@config('installer.app_name')}} - {{@$title}}
        </h4>
      </div>
       @yield('content')
             
    </main>


    <script  src="{{asset('assets/theme/global/js/jquery-3.7.1.min.js')}}"></script>
    <script  src="{{asset('assets/theme/global/js/bootstrap.bundle.min.js')}}"></script>
    <script  src="{{asset('assets/theme/global/js/helper.js')}}"></script>
    <script src="{{asset('assets/theme/global/js/toastr.js')}}"></script>
    

    @include('partials.notify')
    @stack('script-include')
    @stack('script-push')


    <script >
      'use strict'


     $('.ai--btn').click(function(){
          var $html = '<span></span><span></span><span></span>';
          $(this).html($html);
     });

     $(document).on('click','.toggle-password',function(e){

           e.preventDefault()

           var parentAuthInput = $(this).closest('.auth-input');
           var passwordField = parentAuthInput.find('.toggle-input');
           var fieldType = passwordField.attr('type') === 'password' ? 'text' : 'password';
           passwordField.attr('type', fieldType);
           var toggleIcon = parentAuthInput.find('.toggle-icon');
           toggleIcon.toggleClass('bi-eye bi-eye-slash');
     });



      var activeItem = document.querySelector('li.active');
       if (activeItem) {
         var listItems = document.querySelectorAll('ul li');
         listItems.forEach(function(item, index) {
           if (item === activeItem) {
             for (var i = 0; i < index; i++) {
               listItems[i].classList.add('active');
             }
           }
         });
       }
       
   </script>

    {{-- CSRF Token Auto-Refresh for Long Installation Sessions --}}
    <script>
    (function() {
        'use strict';

        // Refresh CSRF token every 15 minutes to prevent 419 errors
        const CSRF_REFRESH_INTERVAL = 15 * 60 * 1000; // 15 minutes

        function refreshCsrfToken() {
            fetch('{{ route("install.csrf.refresh") }}', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.token) {
                    // Update meta tag
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.token);
                    // Update all hidden CSRF inputs
                    document.querySelectorAll('input[name="_token"]').forEach(input => {
                        input.value = data.token;
                    });
                    console.log('CSRF token refreshed successfully');
                }
            })
            .catch(error => {
                console.warn('CSRF refresh failed, will retry:', error.message);
            });
        }

        // Refresh token periodically
        setInterval(refreshCsrfToken, CSRF_REFRESH_INTERVAL);

        // Also refresh when page becomes visible again (user returns to tab)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                refreshCsrfToken();
            }
        });

        // Handle 419 errors gracefully
        $(document).ajaxError(function(event, xhr, settings) {
            if (xhr.status === 419) {
                refreshCsrfToken();
                toastr.warning('Session refreshed. Please try again.', 'Session Expired');
            }
        });
    })();
    </script>

  </body>
