@extends($themeManager->view('layouts.main'))
@section('content')
    @include($themeManager->view('blog.section.breadcrumb_banner'), ['title' => $title])

    <section class="blog pt-120">
      <div class="container-fluid container-wrapper">
        <div class="row g-0 justify-content-center">
          <div class="col-xl-10">
            <div class="blog-detail">
              <div class="detail-img fade-item">
                 <img src="{{showImage(config("setting.file_path.blog_images.path").'/'.$blog->image,config("setting.file_path.blog_images.size"))}}" alt="{{ $blog->image }}" />
              </div>

              <h3 class="blog-detail-title title-anim">
                {{$blog->title}}
              </h3>

              <p>{{$blog->created_at->toDayDateTimeString()}}</p>

              <div class="blog-description">
                <p class="lines-anim">
                   @php echo $blog->description @endphp
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
@endsection
