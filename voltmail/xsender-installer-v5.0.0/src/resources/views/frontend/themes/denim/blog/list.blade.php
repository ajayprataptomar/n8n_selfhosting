@extends($themeManager->view('layouts.main'))
@section('content')
    @include($themeManager->view('blog.section.breadcrumb_banner'), ['title' => $title])
    
    <section class="blog pt-120">
        <div class="container-fluid container-wrapper">
        <div class="row gx-xxl-5 g-4">
            @forelse($blogs as $blog)
                <div class="col-xl-4 col-md-6 fade-item">
                <div class="blog-card">
                    <div class="blog-img">
                        <img src="{{ showImage(config("setting.file_path.blog_images.path").'/'.@$blog->image, config("setting.file_path.blog_images.size")) }}" alt="blog" />
                    </div>

                    <a href="{{ route('blog', ['uid' => $blog->uid]) }}" class="blog-title">
                    <h4>{{ $blog->title }}</h4>
                    </a>
                    <p class="fs-14 mt-2">
                    {{ (limit_html_descriptions(text: $blog->description, ellipsis: true)) }}
                    </p>

                    <a href="{{ route("blog") }}" class="blog-view-more-btn">
                    <span>{{ translate("View more") }}</span>
                    <i class="bi bi-chevron-right fs-14"></i>
                    </a>
                </div>
                </div>
            @empty
                <div class="col-12">
                    <p>{{translate("No blogs found.")}}</p>
                </div>
            @endforelse
        </div>
        </div>
    </section>
@endsection